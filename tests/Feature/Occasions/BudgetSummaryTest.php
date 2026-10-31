<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use App\Services\BudgetSummary;

/**
 * @param  array<int, int|null>  $budgets  recipient name index => budget cents
 * @return array{0: Occasion, 1: User, 2: array<int, Recipient>}
 */
function summaryOccasion(array $budgets = [null]): array
{
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $people = [];

    foreach ($budgets as $budget) {
        $person = Recipient::factory()->create(['household_id' => $user->household_id]);
        $occasion->recipients()->attach($person->id, ['budget_cents' => $budget]);
        $people[] = $person;
    }

    return [$occasion, $user, $people];
}

/**
 * @return array{people: array<int, array{budget: int|null, spent: int, remaining: int|null}>, budget: int, spent: int, remaining: int, noGift: list<string>}
 */
function summarize(Occasion $occasion): array
{
    $gifts = Gift::query()->where('occasion_id', $occasion->id)->get()->groupBy('recipient_id')->all();

    return (new BudgetSummary)->summarize($occasion->recipients()->get(), $gifts);
}

it('adds up what has been spent on a person from bought, wrapped and given gifts', function () {
    [$occasion, $user, [$person]] = summaryOccasion();
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => 1000]);
    Gift::factory()->forPersonOn($person, $occasion)->wrapped($user)->create(['price_cents' => 2000]);
    Gift::factory()->forPersonOn($person, $occasion)->given($user)->create(['price_cents' => 4000]);

    expect(summarize($occasion)['people'][$person->id]['spent'])->toBe(7000);
});

it('does not count ideas as spent', function () {
    [$occasion, $user, [$person]] = summaryOccasion();
    Gift::factory()->forPersonOn($person, $occasion)->create(['price_cents' => 5000]);
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => 1000]);

    expect(summarize($occasion)['people'][$person->id]['spent'])->toBe(1000);
});

it('counts a bought gift with no price as nothing spent', function () {
    [$occasion, $user, [$person]] = summaryOccasion();
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => null]);

    expect(summarize($occasion)['people'][$person->id]['spent'])->toBe(0);
});

it('shows how much of a person\'s budget is left', function () {
    [$occasion, $user, [$person]] = summaryOccasion([10000]);
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => 3000]);

    expect(summarize($occasion)['people'][$person->id])->toBe(['budget' => 10000, 'spent' => 3000, 'remaining' => 7000]);
});

it('shows a negative remaining when a person is over budget', function () {
    [$occasion, $user, [$person]] = summaryOccasion([2000]);
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => 3500]);

    expect(summarize($occasion)['people'][$person->id]['remaining'])->toBe(-1500);
});

it('has no remaining for a person with no budget', function () {
    [$occasion, $user, [$person]] = summaryOccasion([null]);
    Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create(['price_cents' => 3500]);

    expect(summarize($occasion)['people'][$person->id])->toBe(['budget' => null, 'spent' => 3500, 'remaining' => null]);
});

it('totals budget, spent and remaining for the whole occasion', function () {
    [$occasion, $user, [$a, $b, $c]] = summaryOccasion([10000, 5000, null]);
    Gift::factory()->forPersonOn($a, $occasion)->bought($user)->create(['price_cents' => 3000]);
    Gift::factory()->forPersonOn($b, $occasion)->given($user)->create(['price_cents' => 6000]);
    Gift::factory()->forPersonOn($c, $occasion)->wrapped($user)->create(['price_cents' => 1000]);

    expect(summarize($occasion))->toMatchArray(['budget' => 15000, 'spent' => 10000, 'remaining' => 5000]);
});

it('lists the people who have no gifts yet', function () {
    [$occasion, $user, [$a, $b, $c]] = summaryOccasion([null, null, null]);
    Gift::factory()->forPersonOn($b, $occasion)->create();

    $expected = $occasion->recipients()->get()->reject(fn ($p) => $p->id === $b->id)->pluck('name')->values()->all();

    expect(summarize($occasion)['noGift'])->toBe($expected)->toHaveCount(2);
});
