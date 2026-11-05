<?php

declare(strict_types=1);

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use App\Services\Dashboard;
use Carbon\CarbonImmutable;

function dashSummary(User $user, string $today = '2026-11-04'): ?array
{
    return app(Dashboard::class)->christmas($user, CarbonImmutable::parse($today));
}

function dashChristmas(User $user, int $year = 2026): Occasion
{
    return Occasion::factory()->forHousehold($user->household)->christmas($year)->create();
}

function dashPerson(Occasion $occasion, ?int $budget = null): Recipient
{
    $person = Recipient::factory()->create(['household_id' => $occasion->household_id]);
    $occasion->recipients()->attach($person->id, ['budget_cents' => $budget]);

    return $person;
}

it('picks the nearest Christmas still to come', function () {
    $user = User::factory()->create();
    dashChristmas($user, 2025);
    $next = dashChristmas($user, 2026);
    dashChristmas($user, 2027);

    expect(dashSummary($user)['occasion']->is($next))->toBeTrue();
});

it('falls back to the latest Christmas once every Christmas has passed', function () {
    $user = User::factory()->create();
    dashChristmas($user, 2025);
    $latest = dashChristmas($user, 2026);

    expect(dashSummary($user, '2027-01-10')['occasion']->is($latest))->toBeTrue();
});

it('returns nothing when the household has no Christmas', function () {
    $user = User::factory()->create();

    expect(dashSummary($user))->toBeNull();
});

it('totals the Christmas budget and what has been spent', function () {
    $user = User::factory()->create();
    $occasion = dashChristmas($user);
    $person = dashPerson($occasion, 20000);
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => 6000]);
    Gift::factory()->forPersonOn($person, $occasion)->create(['price_cents' => 9000]);

    expect(dashSummary($user))->toMatchArray(['budget' => 20000, 'spent' => 6000, 'remaining' => 14000]);
});

it('counts gifts by status including statuses with none', function () {
    $user = User::factory()->create();
    $occasion = dashChristmas($user);
    $person = dashPerson($occasion);
    Gift::factory()->count(2)->forPersonOn($person, $occasion)->create();
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create();

    expect(dashSummary($user)['counts'])->toBe(['idea' => 2, 'bought' => 1, 'wrapped' => 0, 'given' => 0]);
});

it("leaves my partner's private gifts out of my totals and counts", function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $occasion = dashChristmas($user);
    $person = dashPerson($occasion, 10000);
    Gift::factory()->forPersonOn($person, $occasion)->bought($partner)->private($partner)->create(['price_cents' => 5000]);

    $summary = dashSummary($user);

    expect($summary)->toMatchArray(['spent' => 0, 'remaining' => 10000, 'privateSpent' => null])
        ->and(array_sum($summary['counts']))->toBe(0);
});

it('reports what I have spent on my own private gifts', function () {
    $user = User::factory()->create();
    $occasion = dashChristmas($user);
    $person = dashPerson($occasion, 10000);
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->private($user)->create(['price_cents' => 5000]);

    $summary = dashSummary($user);

    expect($summary['privateSpent'])->toBe(5000)
        ->and($summary['counts'][GiftStatus::Bought->value])->toBe(1);
});

it("never shows another household's Christmas", function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    dashChristmas($other);

    expect(dashSummary($user))->toBeNull();
});

it('counts the days until Christmas, negative once it has passed', function () {
    $user = User::factory()->create();
    dashChristmas($user);

    expect(dashSummary($user, '2026-12-20')['days'])->toBe(5)
        ->and(dashSummary($user, '2027-01-04')['days'])->toBe(-10);
});
