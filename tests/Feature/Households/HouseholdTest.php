<?php

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\QueryException;

it('belongs to a household', function () {
    $household = Household::factory()->create();
    $user = User::factory()->forHousehold($household)->create();

    expect($user->household->is($household))->toBeTrue();
});

it('lists the users of a household', function () {
    $household = Household::factory()->create();
    $users = User::factory()->count(2)->forHousehold($household)->create();
    User::factory()->create();

    expect($household->users->pluck('id')->all())->toEqualCanonicalizing($users->pluck('id')->all());
});

it('creates users in their own household by default in the factory', function () {
    [$a, $b] = User::factory()->count(2)->create();

    expect($a->household_id)->not->toBeNull()
        ->and($a->household_id)->not->toBe($b->household_id);
});

it('puts two users in the same household with the forHousehold factory state', function () {
    $household = Household::factory()->create();
    [$a, $b] = User::factory()->count(2)->forHousehold($household)->create();

    expect($a->household_id)->toBe($household->id)
        ->and($b->household_id)->toBe($household->id);
});

it('prevents deleting a household that still has users', function () {
    $household = User::factory()->create()->household;

    expect(fn () => $household->delete())->toThrow(QueryException::class);
});
