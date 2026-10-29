<?php

declare(strict_types=1);

use App\Actions\People\ArchivePerson;
use App\Actions\People\CreatePerson;
use App\Actions\People\RestorePerson;
use App\Actions\People\UpdatePerson;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('adds a person to my household', function () {
    $user = User::factory()->create();

    $person = app(CreatePerson::class)->handle($user, [
        'name' => 'Mom',
        'group' => 'family',
        'is_shared' => false,
        'birthday_month' => 3,
        'birthday_day' => 14,
        'birthday_year' => '',
        'interests' => '',
    ]);

    expect($person->household_id)->toBe($user->household_id)
        ->and($person->name)->toBe('Mom')
        ->and($person->birthday_month)->toBe(3)
        ->and($person->birthday_year)->toBeNull()
        ->and($person->interests)->toBeNull();
});

it('adds a shared entry like the smiths', function () {
    $person = app(CreatePerson::class)->handle(User::factory()->create(), [
        'name' => 'The Smiths',
        'group' => 'friends',
        'is_shared' => true,
    ]);

    expect($person->is_shared)->toBeTrue()
        ->and($person->fresh()->name)->toBe('The Smiths');
});

it('updates one of my people', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create(['interests' => 'golf']);

    app(UpdatePerson::class)->handle($user, $person->id, [
        'name' => 'Dad',
        'group' => 'family',
        'is_shared' => false,
        'interests' => '',
    ]);

    expect($person->fresh())->name->toBe('Dad')->interests->toBeNull();
});

it('archives one of my people', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    app(ArchivePerson::class)->handle($user, $person->id);

    expect($person->fresh()->archived_at)->not->toBeNull();
});

it('restores an archived person', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->archived()->create();

    app(RestorePerson::class)->handle($user, $person->id);

    expect($person->fresh()->archived_at)->toBeNull();
});

it('cannot update, archive or restore another household\'s person', function () {
    $user = User::factory()->create();
    $other = Recipient::factory()->forHousehold(User::factory()->create()->household)->create();

    expect(fn () => app(UpdatePerson::class)->handle($user, $other->id, ['name' => 'X']))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ArchivePerson::class)->handle($user, $other->id))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(RestorePerson::class)->handle($user, $other->id))
        ->toThrow(ModelNotFoundException::class);
});
