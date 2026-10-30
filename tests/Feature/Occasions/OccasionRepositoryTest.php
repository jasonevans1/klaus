<?php

declare(strict_types=1);

use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use App\Repositories\OccasionRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function occasions(): OccasionRepository
{
    return app(OccasionRepository::class);
}

it('lists my household\'s occasions newest first', function () {
    $user = User::factory()->create();
    $old = Occasion::factory()->forHousehold($user->household)->christmas(2025)->create();
    $new = Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();

    expect(occasions()->listForUser($user)->pluck('id')->all())->toBe([$new->id, $old->id]);
});

it('never lists another household\'s occasions', function () {
    $user = User::factory()->create();
    Occasion::factory()->create();

    expect(occasions()->listForUser($user))->toBeEmpty();
});

it('does not find another household\'s occasion', function () {
    $user = User::factory()->create();
    $other = Occasion::factory()->create();

    occasions()->findForUser($user, $other->id);
})->throws(ModelNotFoundException::class);

it('groups an occasion\'s people in group order with their budgets', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $friend = Recipient::factory()->forHousehold($user->household)->create(['group' => 'friends']);
    $family = Recipient::factory()->forHousehold($user->household)->create(['group' => 'family']);
    $occasion->recipients()->attach($friend, ['budget_cents' => 2500]);
    $occasion->recipients()->attach($family, ['budget_cents' => 10000]);

    $grouped = occasions()->groupedPeople($occasion);

    expect(array_keys($grouped))->toBe(['family', 'friends'])
        ->and($grouped['family']->first()->pivot->budget_cents)->toBe(10000)
        ->and($grouped['friends']->first()->pivot->budget_cents)->toBe(2500);
});

it('keeps a person on the occasion after they are archived', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $person = Recipient::factory()->forHousehold($user->household)->archived()->create(['group' => 'family']);
    $occasion->recipients()->attach($person);

    expect(occasions()->groupedPeople($occasion)['family']->pluck('id')->all())->toBe([$person->id]);
});

it('totals the budgets ignoring people with no budget', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $people = Recipient::factory()->forHousehold($user->household)->count(3)->create();
    $occasion->recipients()->attach($people[0], ['budget_cents' => 2500]);
    $occasion->recipients()->attach($people[1], ['budget_cents' => 1000]);
    $occasion->recipients()->attach($people[2]);

    expect(occasions()->totalBudgetCents($occasion))->toBe(3500);
});

it('offers only my non-archived people who are not already on the occasion', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $on = Recipient::factory()->forHousehold($user->household)->create(['name' => 'On']);
    $zed = Recipient::factory()->forHousehold($user->household)->create(['name' => 'zed']);
    $amy = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Amy']);
    Recipient::factory()->forHousehold($user->household)->archived()->create();
    Recipient::factory()->create();
    $occasion->recipients()->attach($on);

    expect(occasions()->availablePeople($user, $occasion)->pluck('id')->all())->toBe([$amy->id, $zed->id]);
});
