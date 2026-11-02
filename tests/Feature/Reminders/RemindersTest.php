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

it('links a birthday to its birthday occasion when there is one', function () {
    $user = User::factory()->create();
    $house = $user->household;
    $linked = Recipient::factory()->forHousehold($house)->withBirthday(11, 10)->create(['name' => 'Linked']);
    Recipient::factory()->forHousehold($house)->withBirthday(11, 12)->create(['name' => 'Bare']);
    $occasion = Occasion::factory()->birthday($linked, '2026-11-10')->create();

    $birthdays = reminders($user)['birthdays'];

    expect($birthdays[0]['occasionId'])->toBe($occasion->id)
        ->and($birthdays[1]['occasionId'])->toBeNull();
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
    $annParty = Occasion::factory()->birthday($ann, '2026-11-10')->create();
    $bobParty = Occasion::factory()->birthday($bob, '2026-11-12')->create();
    $annParty->recipients()->attach($ann);
    $bobParty->recipients()->attach($bob);
    Gift::factory()->forPersonOn($bob, $bobParty)->create();

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
