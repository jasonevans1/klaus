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

    expect(array_map(fn ($row) => $row['occasion']->id, upcomingFor($user)))->toBe([$sooner->id, $later->id]);
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

    expect(array_map(fn ($row) => $row['occasion']->id, upcomingFor($user)))->toBe([$other->id]);
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
