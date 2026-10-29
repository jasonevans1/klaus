<?php

declare(strict_types=1);

use App\Models\Recipient;
use App\Models\User;
use Livewire\Livewire;

function modalPage(User $user)
{
    return Livewire::actingAs($user)->test('pages::people.index');
}

it('adds a person from the modal', function () {
    $user = User::factory()->create();

    modalPage($user)->call('create')
        ->set('form.name', 'Gran')
        ->set('form.group', 'family')
        ->set('form.birthday_month', '3')
        ->set('form.birthday_day', '9')
        ->set('form.interests', 'Tea')
        ->call('save')
        ->assertHasNoErrors();

    $person = Recipient::where('household_id', $user->household_id)->firstOrFail();
    expect($person->name)->toBe('Gran')
        ->and($person->birthday_month)->toBe(3)
        ->and($person->interests)->toBe('Tea');
});

it('adds a shared entry from the modal', function () {
    $user = User::factory()->create();

    modalPage($user)->call('create')
        ->set('form.name', 'The Smiths')
        ->set('form.is_shared', true)
        ->call('save');

    expect(Recipient::where('name', 'The Smiths')->firstOrFail()->is_shared)->toBeTrue();
});

it('saves and keeps the modal ready for the next person in the same group', function () {
    $user = User::factory()->create();

    modalPage($user)->call('create')
        ->set('form.name', 'Ann')
        ->set('form.group', 'friends')
        ->call('saveAndAddAnother')
        ->assertSet('form.name', '')
        ->assertSet('form.group', 'friends');

    expect(Recipient::where('name', 'Ann')->exists())->toBeTrue();
});

it('edits an existing person', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Old']);

    modalPage($user)->call('edit', $person->id)
        ->assertSet('form.name', 'Old')
        ->set('form.name', 'New')
        ->call('save');

    expect($person->fresh()->name)->toBe('New');
});

it('adds a new person after editing another', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Old']);

    modalPage($user)->call('edit', $person->id)
        ->call('create')
        ->set('form.name', 'Fresh')
        ->call('save');

    expect($person->fresh()->name)->toBe('Old')
        ->and(Recipient::where('household_id', $user->household_id)->count())->toBe(2);
});

it('shows validation errors in the modal', function () {
    $user = User::factory()->create();

    modalPage($user)->call('create')->call('save')->assertHasErrors(['form.name' => 'required']);
});

it("cannot open another household's person for editing", function () {
    $user = User::factory()->create();
    $other = Recipient::factory()->create();

    modalPage($user)->call('edit', $other->id)->assertNotFound();
});
