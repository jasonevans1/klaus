<?php

declare(strict_types=1);

use App\Actions\Occasions\CreateUpcomingBirthdays;
use App\Enums\OccasionType;
use App\Models\Household;
use App\Models\Occasion;
use App\Models\Recipient;
use Carbon\CarbonImmutable;

function annaWithBirthday(int $month, int $day, ?Household $household = null): Recipient
{
    $factory = Recipient::factory()->withBirthday($month, $day);

    if ($household !== null) {
        $factory = $factory->forHousehold($household);
    }

    return $factory->create(['name' => 'Anna']);
}

it('creates a birthday occasion for a birthday in the next 30 days with the person on it', function () {
    $anna = annaWithBirthday(11, 20);

    $created = app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    $occasion = Occasion::query()->sole();
    expect($created)->toBe(1)
        ->and($occasion->type)->toBe(OccasionType::Birthday)
        ->and($occasion->name)->toBe("Anna's birthday 2026")
        ->and($occasion->date->toDateString())->toBe('2026-11-20')
        ->and($occasion->household_id)->toBe($anna->household_id)
        ->and($occasion->birthday_recipient_id)->toBe($anna->id)
        ->and($occasion->recipients()->pluck('recipients.id')->all())->toBe([$anna->id])
        ->and($anna->refresh()->last_birthday_year)->toBe(2026);
});

it('does not create a birthday more than 30 days away', function () {
    annaWithBirthday(12, 2);

    $created = app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    expect($created)->toBe(0)->and(Occasion::query()->count())->toBe(0);
});

it("creates next year's birthday when this year's has passed and it is within 30 days", function () {
    $anna = annaWithBirthday(1, 10);

    app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 12, 20));

    expect(Occasion::query()->sole()->date->toDateString())->toBe('2027-01-10')
        ->and($anna->refresh()->last_birthday_year)->toBe(2027);
});

it('creates each birthday only once when run again', function () {
    annaWithBirthday(11, 20);
    $today = CarbonImmutable::create(2026, 11, 1);

    app(CreateUpcomingBirthdays::class)->handle($today);
    $second = app(CreateUpcomingBirthdays::class)->handle($today);

    expect($second)->toBe(0)->and(Occasion::query()->count())->toBe(1);
});

it('does not recreate a birthday that was deleted', function () {
    annaWithBirthday(11, 20);
    $today = CarbonImmutable::create(2026, 11, 1);

    app(CreateUpcomingBirthdays::class)->handle($today);
    Occasion::query()->sole()->delete();
    app(CreateUpcomingBirthdays::class)->handle($today);

    expect(Occasion::query()->count())->toBe(0);
});

it('skips archived people and people without a birthday', function () {
    Recipient::factory()->withBirthday(11, 20)->archived()->create(['name' => 'Bob']);
    Recipient::factory()->create(['name' => 'Cleo']);

    $created = app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    expect($created)->toBe(0)->and(Occasion::query()->count())->toBe(0);
});

it('creates birthdays for every household in that household', function () {
    $first = Household::factory()->create();
    $second = Household::factory()->create();
    annaWithBirthday(11, 20, $first);
    annaWithBirthday(11, 21, $second);

    $created = app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    expect($created)->toBe(2)
        ->and(Occasion::query()->where('household_id', $first->id)->count())->toBe(1)
        ->and(Occasion::query()->where('household_id', $second->id)->count())->toBe(1);
});

it("carries over the budget from the person's last birthday", function () {
    $anna = annaWithBirthday(11, 20);
    Occasion::factory()->birthday($anna, '2025-11-20')->create()->recipients()->attach($anna->id, ['budget_cents' => 5000]);

    app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    $new = Occasion::query()->orderByDesc('date')->first();
    expect($new->date->toDateString())->toBe('2026-11-20')
        ->and($new->recipients()->whereKey($anna->id)->first()->pivot->budget_cents)->toBe(5000);
});

it('leaves the budget empty for a first birthday', function () {
    $anna = annaWithBirthday(11, 20);

    app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    expect(Occasion::query()->sole()->recipients()->whereKey($anna->id)->first()->pivot->budget_cents)->toBeNull();
});

it('puts a Feb 29 birthday on Feb 28 in a non-leap year', function () {
    annaWithBirthday(2, 29);

    app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2027, 2, 10));

    expect(Occasion::query()->sole()->date->toDateString())->toBe('2027-02-28');
});

it('names the occasion after the person and year', function () {
    annaWithBirthday(11, 20);

    app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    expect(Occasion::query()->sole()->name)->toBe("Anna's birthday 2026");
});

it('names a birthday for a name ending in s without an extra s', function () {
    Recipient::factory()->withBirthday(11, 20)->create(['name' => 'James']);

    app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    expect(Occasion::query()->sole()->name)->toBe("James' birthday 2026");
});

it('keeps a very long name within 100 characters and still ends with the year', function () {
    Recipient::factory()->withBirthday(11, 20)->create(['name' => str_repeat('é', 100)]);

    app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    $name = Occasion::query()->sole()->name;
    expect(mb_strlen($name))->toBeLessThanOrEqual(100)->and($name)->toEndWith("'s birthday 2026");
});

it('creates a birthday exactly 30 days away', function () {
    annaWithBirthday(12, 1);

    expect(app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1)))->toBe(1);
});

it('creates a birthday that is today', function () {
    annaWithBirthday(11, 1);

    app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    expect(Occasion::query()->sole()->date->toDateString())->toBe('2026-11-01');
});

it('carries over the budget from the most recent of several past birthdays', function () {
    $anna = annaWithBirthday(11, 20);
    Occasion::factory()->birthday($anna, '2024-11-20')->create()->recipients()->attach($anna->id, ['budget_cents' => 3000]);
    Occasion::factory()->birthday($anna, '2025-11-20')->create()->recipients()->attach($anna->id, ['budget_cents' => 5000]);

    app(CreateUpcomingBirthdays::class)->handle(CarbonImmutable::create(2026, 11, 1));

    $new = Occasion::query()->orderByDesc('date')->first();
    expect($new->recipients()->whereKey($anna->id)->first()->pivot->budget_cents)->toBe(5000);
});
