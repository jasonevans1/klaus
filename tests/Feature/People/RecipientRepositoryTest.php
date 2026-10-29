<?php

declare(strict_types=1);

use App\Models\Recipient;
use App\Models\User;
use App\Repositories\RecipientRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function repo(): RecipientRepository
{
    return app(RecipientRepository::class);
}

it('groups people in family friends teachers coworkers order', function () {
    $user = User::factory()->create();
    foreach (['coworkers', 'teachers', 'friends', 'family'] as $group) {
        Recipient::factory()->forHousehold($user->household)->create(['group' => $group]);
    }

    expect(array_keys(repo()->groupedForUser($user)))
        ->toBe(['family', 'friends', 'teachers', 'coworkers']);
});

it('sorts people by name within a group ignoring case', function () {
    $user = User::factory()->create();
    foreach (['bob', 'Carl', 'alice', 'Dave'] as $name) {
        Recipient::factory()->forHousehold($user->household)->create(['group' => 'family', 'name' => $name]);
    }

    expect(repo()->groupedForUser($user)['family']->pluck('name')->all())
        ->toBe(['alice', 'bob', 'Carl', 'Dave']);
});

it('leaves out archived people by default', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create(['group' => 'family', 'name' => 'Kept']);
    Recipient::factory()->forHousehold($user->household)->archived()->create(['group' => 'family', 'name' => 'Gone']);

    expect(repo()->groupedForUser($user)['family']->pluck('name')->all())->toBe(['Kept']);
});

it('includes archived people when asked', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create(['group' => 'family', 'name' => 'Kept']);
    Recipient::factory()->forHousehold($user->household)->archived()->create(['group' => 'family', 'name' => 'Gone']);

    expect(repo()->groupedForUser($user, true)['family']->pluck('name')->all())->toBe(['Gone', 'Kept']);
});

it('never returns another household\'s people', function () {
    $user = User::factory()->create();
    Recipient::factory()->create(['group' => 'family']);

    expect(repo()->groupedForUser($user, true))->toBe([]);
});

it('finds one of my people including archived ones', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->archived()->create();

    expect(repo()->findForUser($user, $person->id)->is($person))->toBeTrue();
});

it('does not find another household\'s person', function () {
    $user = User::factory()->create();
    $other = Recipient::factory()->create();

    repo()->findForUser($user, $other->id);
})->throws(ModelNotFoundException::class);
