<?php

declare(strict_types=1);

use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Livewire;

function addPeopleSetup(): array
{
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();
    $occasion->recipients()->attach(Recipient::factory()->forHousehold($user->household)->create(['name' => 'Mum']));
    $anna = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Anna']);
    $bob = Recipient::factory()->forHousehold($user->household)->create(['name' => 'Bob']);
    Recipient::factory()->create(['name' => 'Stranger']);

    return [$user, $occasion, null, $anna, $bob];
}

it('lists only my people who are not on the occasion yet', function () {
    [$user, $occasion] = addPeopleSetup();

    Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id])->call('openAddPeople')
        ->assertSeeInOrder(['Anna', 'Bob'])
        ->assertDontSeeHtml('data-available="Mum"')
        ->assertDontSee('Stranger');
});

it('adds the selected people', function () {
    [$user, $occasion, , $anna] = addPeopleSetup();

    Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id])->call('openAddPeople')
        ->set('selected', [(string) $anna->id])
        ->call('addSelected')
        ->assertHasNoErrors();

    expect($occasion->recipients()->pluck('recipients.id')->all())->toContain($anna->id)
        ->and($occasion->recipients()->count())->toBe(2);
});

it('adds everyone with add all', function () {
    [$user, $occasion] = addPeopleSetup();

    Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id])->call('openAddPeople')->call('addAll');

    expect($occasion->recipients()->count())->toBe(3);
});

it('asks me to pick someone when nothing is selected', function () {
    [$user, $occasion] = addPeopleSetup();

    Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id])->call('openAddPeople')->call('addSelected')
        ->assertHasErrors('selected');

    expect($occasion->recipients()->count())->toBe(1);
});

it('shows newly added people with an empty budget', function () {
    [$user, $occasion, , $anna] = addPeopleSetup();

    Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id])->call('openAddPeople')
        ->set('selected', [(string) $anna->id])
        ->call('addSelected')
        ->assertSet('budgets.'.$anna->id, '')
        ->assertSeeHtml('data-person="Anna"');
});

it('says everyone is already on the occasion when nobody is left', function () {
    [$user, $occasion] = addPeopleSetup();

    Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id])->call('openAddPeople')->call('addAll')
        ->assertSee('Everyone is already on this occasion.');
});
