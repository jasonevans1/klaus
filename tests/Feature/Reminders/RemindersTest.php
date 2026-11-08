<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use App\Services\Reminders;
use Carbon\CarbonImmutable;

function remindersToday(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-01 15:30:00');
}

function reminders(User $user): array
{
    return app(Reminders::class)->for($user, remindersToday());
}

it('lists birthdays in the next 30 days, soonest first', function () {
    $user = User::factory()->create();
    $house = $user->household;
    Recipient::factory()->forHousehold($house)->withBirthday(11, 20)->create(['name' => 'Later']);
    Recipient::factory()->forHousehold($house)->withBirthday(11, 2)->create(['name' => 'Tomorrow']);
    Recipient::factory()->forHousehold($house)->withBirthday(11, 1)->create(['name' => 'Today']);

    $birthdays = reminders($user)['birthdays'];

    expect(array_column($birthdays, 'name'))->toBe(['Today', 'Tomorrow', 'Later'])
        ->and(array_column($birthdays, 'days'))->toBe([0, 1, 19])
        ->and($birthdays[0]['date']->toDateString())->toBe('2026-11-01');
});

it('leaves out archived people and birthdays more than 30 days away', function () {
    $user = User::factory()->create();
    $house = $user->household;
    Recipient::factory()->forHousehold($house)->withBirthday(12, 1)->create(['name' => 'Edge']);
    Recipient::factory()->forHousehold($house)->withBirthday(12, 2)->create(['name' => 'Far']);
    Recipient::factory()->forHousehold($house)->withBirthday(11, 5)->archived()->create(['name' => 'Archived']);

    expect(array_column(reminders($user)['birthdays'], 'name'))->toBe(['Edge']);
});

function birthdaysFor(User $user, int $year, Recipient ...$people): Occasion
{
    $occasion = Occasion::factory()->forHousehold($user->household)->birthdays($year)->create();
    $occasion->recipients()->attach(array_map(fn (Recipient $person): int => $person->id, $people));

    return $occasion;
}

it('links an upcoming birthday to that year\'s Birthdays occasion', function () {
    $user = User::factory()->create();
    $ann = Recipient::factory()->forHousehold($user->household)->withBirthday(11, 10)->create();
    $occasion = birthdaysFor($user, 2026, $ann);

    expect(reminders($user)['birthdays'][0]['occasionId'])->toBe($occasion->id);
});

it('says no gift yet when the person has no gift on the Birthdays occasion', function () {
    $user = User::factory()->create();
    $ann = Recipient::factory()->forHousehold($user->household)->withBirthday(11, 10)->create();
    birthdaysFor($user, 2026, $ann);

    expect(reminders($user)['birthdays'][0]['needsGift'])->toBeTrue();
});

it('stops saying no gift yet once the person has a gift there', function () {
    $user = User::factory()->create();
    $ann = Recipient::factory()->forHousehold($user->household)->withBirthday(11, 10)->create();
    Gift::factory()->forPersonOn($ann, birthdaysFor($user, 2026, $ann))->create();

    expect(reminders($user)['birthdays'][0]['needsGift'])->toBeFalse();
});

it('treats my partner\'s private gift as no gift for me', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $ann = Recipient::factory()->forHousehold($user->household)->withBirthday(11, 10)->create();
    Gift::factory()->forPersonOn($ann, birthdaysFor($user, 2026, $ann))->private($partner)->create();

    expect(reminders($user)['birthdays'][0]['needsGift'])->toBeTrue()
        ->and(reminders($partner)['birthdays'][0]['needsGift'])->toBeFalse();
});

it('links a January birthday seen in December to next year\'s Birthdays occasion', function () {
    $user = User::factory()->create();
    $ann = Recipient::factory()->forHousehold($user->household)->withBirthday(1, 3)->create();
    birthdaysFor($user, 2026, $ann);
    $next = birthdaysFor($user, 2027, $ann);

    $birthdays = app(Reminders::class)->for($user, CarbonImmutable::parse('2026-12-15'))['birthdays'];

    expect($birthdays[0]['occasionId'])->toBe($next->id)
        ->and($birthdays[0]['needsGift'])->toBeTrue();
});

it('shows the birthday without a link when that year has no Birthdays occasion', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->withBirthday(11, 10)->create();

    expect(reminders($user)['birthdays'][0])->toMatchArray(['occasionId' => null, 'needsGift' => false]);
});

it('shows the birthday without a link when the person is not on that year\'s Birthdays occasion', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->withBirthday(11, 10)->create();
    birthdaysFor($user, 2026);

    expect(reminders($user)['birthdays'][0])->toMatchArray(['occasionId' => null, 'needsGift' => false]);
});

it('leaves Birthdays occasions out of the people-with-no-gift banner', function () {
    $user = User::factory()->create();
    $ann = Recipient::factory()->forHousehold($user->household)->withBirthday(11, 10)->create();
    $occasion = birthdaysFor($user, 2026, $ann);
    $occasion->update(['date' => '2026-11-10']);

    expect(reminders($user)['noGift'])->toBe([]);
});

it('lists upcoming occasions with the people who have no gift yet', function () {
    $user = User::factory()->create();
    $house = $user->household;
    $ann = Recipient::factory()->forHousehold($house)->create(['name' => 'Ann']);
    $bob = Recipient::factory()->forHousehold($house)->create(['name' => 'Bob']);
    $cy = Recipient::factory()->forHousehold($house)->create(['name' => 'Cy']);
    $later = Occasion::factory()->forHousehold($house)->create(['name' => 'Later', 'date' => '2026-11-30']);
    $soon = Occasion::factory()->forHousehold($house)->create(['name' => 'Soon', 'date' => '2026-11-10']);
    Occasion::factory()->forHousehold($house)->create(['name' => 'Far', 'date' => '2026-12-25'])->recipients()->attach($ann);
    $soon->recipients()->attach([$ann->id, $bob->id, $cy->id]);
    $later->recipients()->attach($cy);
    Gift::factory()->forPersonOn($ann, $soon)->create();

    $noGift = reminders($user)['noGift'];

    expect(array_column($noGift, 'name'))->toBe(['Soon', 'Later'])
        ->and($noGift[0]['people'])->toBe(['Bob', 'Cy'])
        ->and($noGift[0]['occasionId'])->toBe($soon->id)
        ->and($noGift[0]['days'])->toBe(9)
        ->and($noGift[0]['date']->toDateString())->toBe('2026-11-10')
        ->and($noGift[1]['people'])->toBe(['Cy']);
});

it('leaves out occasions where everyone has a gift', function () {
    $user = User::factory()->create();
    $ann = Recipient::factory()->forHousehold($user->household)->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create(['date' => '2026-11-10']);
    Gift::factory()->forPersonOn($ann, $occasion)->create();

    expect(reminders($user)['noGift'])->toBe([]);
});

it('flags a birthday with no gift yet once, on the birthday, not again as an occasion', function () {
    $user = User::factory()->create();
    $house = $user->household;
    $ann = Recipient::factory()->forHousehold($house)->withBirthday(11, 10)->create(['name' => 'Ann']);
    $bob = Recipient::factory()->forHousehold($house)->withBirthday(11, 12)->create(['name' => 'Bob']);
    Recipient::factory()->forHousehold($house)->withBirthday(11, 14)->create(['name' => 'Cy']);
    $birthdays = birthdaysFor($user, 2026, $ann, $bob);
    Gift::factory()->forPersonOn($bob, $birthdays)->create();

    $result = reminders($user);

    expect(array_column($result['birthdays'], 'needsGift'))->toBe([true, false, false])
        ->and($result['noGift'])->toBe([]);
});

it('counts my private gift for me but not for my partner', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $ann = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Ann']);
    $occasion = Occasion::factory()->forHousehold($user->household)->create(['date' => '2026-11-10']);
    $occasion->recipients()->attach($ann);
    Gift::factory()->forPersonOn($ann, $occasion)->private($user)->create();

    expect(reminders($user)['noGift'])->toBe([])
        ->and(reminders($partner)['noGift'][0]['people'])->toBe(['Ann']);
});

it('leaves out another household\'s people and occasions', function () {
    $user = User::factory()->create();
    $other = Recipient::factory()->withBirthday(11, 5)->create();
    $occasion = Occasion::factory()->forHousehold($other->household)->create(['date' => '2026-11-10']);
    $occasion->recipients()->attach($other);

    expect(reminders($user))->toBe(['birthdays' => [], 'noGift' => []]);
});
