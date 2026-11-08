<?php

declare(strict_types=1);

use App\Enums\OccasionType;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use App\Services\Dashboard;
use Carbon\CarbonImmutable;

function upcomingFor(User $user, string $today = '2026-11-04'): array
{
    return app(Dashboard::class)->upcoming($user, CarbonImmutable::parse($today));
}

function upcomingOccasion(User $user, string $date): Occasion
{
    return Occasion::factory()->forHousehold($user->household)->create(['type' => OccasionType::Birthday, 'name' => "On $date", 'date' => $date]);
}

function upcomingPerson(Occasion $occasion, int $budget): Recipient
{
    $person = Recipient::factory()->create(['household_id' => $occasion->household_id]);
    $occasion->recipients()->attach($person->id, ['budget_cents' => $budget]);

    return $person;
}

it('lists occasions in the next 60 days soonest first', function () {
    $user = User::factory()->create();
    $later = upcomingOccasion($user, '2026-12-01');
    $sooner = upcomingOccasion($user, '2026-11-10');

    expect(array_map(fn ($row) => $row['occasionId'], upcomingFor($user)))->toBe([$sooner->id, $later->id]);
});

it('leaves out occasions more than 60 days away and ones already past', function () {
    $user = User::factory()->create();
    upcomingOccasion($user, '2027-01-04');
    upcomingOccasion($user, '2026-11-03');

    expect(upcomingFor($user))->toBe([]);
});

it('leaves out the current Christmas shown in the card', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();
    $other = upcomingOccasion($user, '2026-12-01');

    expect(array_map(fn ($row) => $row['occasionId'], upcomingFor($user)))->toBe([$other->id]);
});

it('counts the days until each occasion', function () {
    $user = User::factory()->create();
    upcomingOccasion($user, '2026-11-04');
    upcomingOccasion($user, '2026-11-10');

    expect(array_column(upcomingFor($user), 'days'))->toBe([0, 6]);
});

it('totals budget and spent for each occasion', function () {
    $user = User::factory()->create();
    $occasion = upcomingOccasion($user, '2026-11-10');
    $person = upcomingPerson($occasion, 20000);
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => 6000]);

    expect(upcomingFor($user)[0])->toMatchArray(['budget' => 20000, 'spent' => 6000, 'remaining' => 14000]);
});

it("leaves my partner's private gifts out of each occasion's spent", function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $occasion = upcomingOccasion($user, '2026-11-10');
    $person = upcomingPerson($occasion, 10000);
    Gift::factory()->forPersonOn($person, $occasion)->bought($partner)->private($partner)->create(['price_cents' => 5000]);

    expect(upcomingFor($user)[0])->toMatchArray(['spent' => 0, 'remaining' => 10000]);
});

function birthdaysPerson(User $user, Occasion $occasion, string $name, int $month, int $day, ?int $budget = 5000): Recipient
{
    $person = Recipient::factory()->forHousehold($user->household)->withBirthday($month, $day)->create(['name' => $name]);
    $occasion->recipients()->attach($person->id, ['budget_cents' => $budget]);

    return $person;
}

function yearlyBirthdays(User $user, int $year): Occasion
{
    return Occasion::factory()->forHousehold($user->household)->birthdays($year)->create();
}

it("lists a person's birthday in the next 60 days as coming up", function () {
    $user = User::factory()->create();
    birthdaysPerson($user, yearlyBirthdays($user, 2026), 'Pluto', 11, 10);

    expect(upcomingFor($user)[0])->toMatchArray(['name' => "Pluto's birthday", 'days' => 6, 'date' => CarbonImmutable::parse('2026-11-10')]);
});

it("shows that person's spent and budget from the Birthdays occasion", function () {
    $user = User::factory()->create();
    $occasion = yearlyBirthdays($user, 2026);
    $person = birthdaysPerson($user, $occasion, 'Pete', 11, 10, 8000);
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => 3000]);

    expect(upcomingFor($user)[0])->toMatchArray(['name' => "Pete's birthday", 'occasionId' => $occasion->id, 'budget' => 8000, 'spent' => 3000, 'remaining' => 5000]);
});

it("leaves my partner's private gifts out of a birthday's spent", function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $occasion = yearlyBirthdays($user, 2026);
    $person = birthdaysPerson($user, $occasion, 'Pluto', 11, 10);
    Gift::factory()->forPersonOn($person, $occasion)->bought($partner)->private($partner)->create(['price_cents' => 5000]);

    expect(upcomingFor($user)[0]['spent'])->toBe(0);
});

it('does not list the Birthdays occasion itself as coming up', function () {
    $user = User::factory()->create();
    yearlyBirthdays($user, 2026)->update(['date' => '2026-11-10']);

    expect(upcomingFor($user))->toBe([]);
});

it('skips birthdays of people not on that years Birthdays occasion', function () {
    $user = User::factory()->create();
    yearlyBirthdays($user, 2026);
    Recipient::factory()->forHousehold($user->household)->withBirthday(11, 10)->create();

    expect(upcomingFor($user))->toBe([]);
});

it('sorts birthdays and occasions together by date', function () {
    $user = User::factory()->create();
    upcomingOccasion($user, '2026-11-08');
    birthdaysPerson($user, yearlyBirthdays($user, 2026), 'Pluto', 11, 6);
    upcomingOccasion($user, '2026-11-20');
    birthdaysPerson($user, Occasion::query()->where('type', OccasionType::Birthdays)->first(), 'Mum', 11, 15);

    expect(array_column(upcomingFor($user), 'name'))->toBe(["Pluto's birthday", 'On 2026-11-08', "Mum's birthday", 'On 2026-11-20']);
});

it("uses next year's Birthdays occasion for a January birthday seen in December", function () {
    $user = User::factory()->create();
    $next = yearlyBirthdays($user, 2027);
    birthdaysPerson($user, yearlyBirthdays($user, 2026), 'Old', 1, 5);
    birthdaysPerson($user, $next, 'Jan', 1, 5);

    $rows = upcomingFor($user, '2026-12-20');

    expect($rows)->toHaveCount(1)->and($rows[0])->toMatchArray(['name' => "Jan's birthday", 'occasionId' => $next->id]);
});

it('skips archived people on the Birthdays occasion', function () {
    $user = User::factory()->create();
    birthdaysPerson($user, yearlyBirthdays($user, 2026), 'Gone', 11, 10)->forceFill(['archived_at' => now()])->save();

    expect(upcomingFor($user))->toBe([]);
});
