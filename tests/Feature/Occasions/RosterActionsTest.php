<?php

declare(strict_types=1);

use App\Actions\Occasions\AddPeopleToOccasion;
use App\Actions\Occasions\RemovePersonFromOccasion;
use App\Actions\Occasions\SetBudget;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function rosterSetup(): array
{
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();

    return [$user, $occasion];
}

it('adds my people to an occasion without a budget', function () {
    [$user, $occasion] = rosterSetup();
    $people = Recipient::factory()->forHousehold($user->household)->count(2)->create();

    $added = app(AddPeopleToOccasion::class)->handle($user, $occasion->id, $people->pluck('id')->all());

    expect($added)->toBe(2)
        ->and($occasion->recipients()->count())->toBe(2)
        ->and($occasion->recipients()->first()->pivot->budget_cents)->toBeNull();
});

it('skips archived people and people already on the occasion', function () {
    [$user, $occasion] = rosterSetup();
    $new = Recipient::factory()->forHousehold($user->household)->create();
    $archived = Recipient::factory()->forHousehold($user->household)->archived()->create();
    $existing = Recipient::factory()->forHousehold($user->household)->create();
    $occasion->recipients()->attach($existing, ['budget_cents' => 500]);

    $added = app(AddPeopleToOccasion::class)->handle($user, $occasion->id, [$new->id, $archived->id, $existing->id]);

    expect($added)->toBe(1)
        ->and($occasion->recipients()->pluck('recipients.id')->all())->toEqualCanonicalizing([$new->id, $existing->id])
        ->and($occasion->recipients()->whereKey($existing->id)->first()->pivot->budget_cents)->toBe(500);
});

it('ignores duplicate ids and adds nobody for an empty list', function () {
    [$user, $occasion] = rosterSetup();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    expect(app(AddPeopleToOccasion::class)->handle($user, $occasion->id, [$person->id, $person->id]))->toBe(1)
        ->and(app(AddPeopleToOccasion::class)->handle($user, $occasion->id, []))->toBe(0);
});

it('saves the same budget twice without error', function () {
    [$user, $occasion] = rosterSetup();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $occasion->recipients()->attach($person);

    app(SetBudget::class)->handle($user, $occasion->id, $person->id, 2500);
    app(SetBudget::class)->handle($user, $occasion->id, $person->id, 2500);

    expect($occasion->recipients()->first()->pivot->budget_cents)->toBe(2500);
});

it('refuses to add another household\'s person', function () {
    [$user, $occasion] = rosterSetup();
    $mine = Recipient::factory()->forHousehold($user->household)->create();
    $theirs = Recipient::factory()->create();

    try {
        app(AddPeopleToOccasion::class)->handle($user, $occasion->id, [$mine->id, $theirs->id]);
    } catch (ModelNotFoundException) {
    }

    expect($occasion->recipients()->count())->toBe(0);
    app(AddPeopleToOccasion::class)->handle($user, $occasion->id, [$mine->id, $theirs->id]);
})->throws(ModelNotFoundException::class);

it('refuses to change another household\'s occasion', function () {
    [$user] = rosterSetup();
    $other = Occasion::factory()->create();
    $theirPerson = Recipient::factory()->forHousehold($other->household)->create();
    $other->recipients()->attach($theirPerson);
    $mine = Recipient::factory()->forHousehold($user->household)->create();

    $attempts = [
        fn () => app(AddPeopleToOccasion::class)->handle($user, $other->id, [$mine->id]),
        fn () => app(RemovePersonFromOccasion::class)->handle($user, $other->id, $theirPerson->id),
        fn () => app(SetBudget::class)->handle($user, $other->id, $theirPerson->id, 100),
    ];

    foreach ($attempts as $attempt) {
        expect($attempt)->toThrow(ModelNotFoundException::class);
    }

    expect($other->recipients()->pluck('recipients.id')->all())->toBe([$theirPerson->id])
        ->and($other->recipients()->first()->pivot->budget_cents)->toBeNull();
});

it('removes a person from an occasion', function () {
    [$user, $occasion] = rosterSetup();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $occasion->recipients()->attach($person);

    app(RemovePersonFromOccasion::class)->handle($user, $occasion->id, $person->id);

    expect($occasion->recipients()->count())->toBe(0)
        ->and(Recipient::find($person->id))->not->toBeNull();
});

it('sets and clears a person\'s budget', function () {
    [$user, $occasion] = rosterSetup();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $occasion->recipients()->attach($person);

    app(SetBudget::class)->handle($user, $occasion->id, $person->id, 5000);
    expect($occasion->recipients()->first()->pivot->budget_cents)->toBe(5000);

    app(SetBudget::class)->handle($user, $occasion->id, $person->id, null);
    expect($occasion->recipients()->first()->pivot->budget_cents)->toBeNull();
});

it('refuses to set a budget for someone not on the occasion', function () {
    [$user, $occasion] = rosterSetup();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    app(SetBudget::class)->handle($user, $occasion->id, $person->id, 100);
})->throws(ModelNotFoundException::class);
