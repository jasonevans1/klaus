<?php

declare(strict_types=1);

use App\Enums\RecipientGroup;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Livewire\Livewire;

function occasionFor(User $user): Occasion
{
    return Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();
}

function personOn(Occasion $occasion, string $name, ?int $cents = null, RecipientGroup $group = RecipientGroup::Family): Recipient
{
    $person = Recipient::factory()->forHousehold($occasion->household)->create(['name' => $name, 'group' => $group]);
    $occasion->recipients()->attach($person, ['budget_cents' => $cents]);

    return $person;
}

function occasionPage(User $user, Occasion $occasion)
{
    return Livewire::actingAs($user)->test('pages::occasions.show', ['occasion' => $occasion->id]);
}

it("shows the occasion's people grouped with their budgets", function () {
    $user = User::factory()->create();
    $occasion = occasionFor($user);
    personOn($occasion, 'Mum', 125000);
    personOn($occasion, 'Dave', null, RecipientGroup::Coworkers);

    occasionPage($user, $occasion)
        ->assertSeeInOrder(['Family (1)', 'Mum', 'Coworkers (1)', 'Dave'])
        ->assertSeeHtml('data-group="Family"')
        ->assertSeeHtml('data-person="Mum"')
        ->assertSeeHtml('data-budget="$1,250"')
        ->assertSeeHtml('data-budget="No budget"')
        ->assertSet('budgets', [$occasion->recipients()->where('name', 'Mum')->value('recipients.id') => '1250', $occasion->recipients()->where('name', 'Dave')->value('recipients.id') => '']);
});

it('shows the total budget', function () {
    $user = User::factory()->create();
    $occasion = occasionFor($user);
    personOn($occasion, 'Mum', 125000);
    personOn($occasion, 'Dad', 5000);

    occasionPage($user, $occasion)->assertSeeHtml('data-total-budget="$1,300"');
});

it('saves a whole-dollar budget and updates the total', function () {
    $user = User::factory()->create();
    $occasion = occasionFor($user);
    $mum = personOn($occasion, 'Mum');

    occasionPage($user, $occasion)
        ->set("budgets.{$mum->id}", '75')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-total-budget="$75"');

    expect($occasion->recipients()->first()->pivot->budget_cents)->toBe(7500);
});

it('clears a budget when the field is emptied', function () {
    $user = User::factory()->create();
    $occasion = occasionFor($user);
    $mum = personOn($occasion, 'Mum', 5000);

    occasionPage($user, $occasion)
        ->set("budgets.{$mum->id}", '')
        ->assertHasNoErrors()
        ->assertSeeHtml('data-total-budget="$0"');

    expect($occasion->recipients()->first()->pivot->budget_cents)->toBeNull();
});

it('shows an error for a budget with cents or below zero', function (string $value) {
    $user = User::factory()->create();
    $occasion = occasionFor($user);
    $mum = personOn($occasion, 'Mum', 5000);

    occasionPage($user, $occasion)
        ->set("budgets.{$mum->id}", $value)
        ->assertHasErrors("budgets.{$mum->id}");

    expect($occasion->recipients()->first()->pivot->budget_cents)->toBe(5000);
})->with(['12.50', '-5']);

it('removes a person from the occasion', function () {
    $user = User::factory()->create();
    $occasion = occasionFor($user);
    $mum = personOn($occasion, 'Mum', 5000);
    personOn($occasion, 'Dad');

    occasionPage($user, $occasion)
        ->call('remove', $mum->id)
        ->assertDontSeeHtml('data-person="Mum"')
        ->assertSeeHtml('data-person="Dad"')
        ->assertSeeHtml('data-total-budget="$0"');

    expect($occasion->recipients()->count())->toBe(1)->and(Recipient::find($mum->id))->not->toBeNull();
});

it('shows an empty state when nobody is on the occasion', function () {
    $user = User::factory()->create();

    occasionPage($user, occasionFor($user))
        ->assertSee('Nobody on this occasion yet.')
        ->assertSee('Add people');
});

// Livewire::test()->set() always reaches the server, so it can't tell `wire:model.blur`
// (Livewire 4: browser-only, never saves) from `wire:model.live.blur` (saves on blur).
it('sends a budget to the server when the box loses focus', function () {
    $user = User::factory()->create();
    $occasion = occasionFor($user);
    $mum = personOn($occasion, 'Mum');

    occasionPage($user, $occasion)
        ->assertSeeHtml('wire:model.live.blur="budgets.'.$mum->id.'"')
        ->assertDontSeeHtml('wire:model.blur=')
        ->assertSeeHtml('data-flux-input-group-prefix');
});

it('shows the budget error under the box', function () {
    $user = User::factory()->create();
    $occasion = occasionFor($user);
    $mum = personOn($occasion, 'Mum');

    occasionPage($user, $occasion)
        ->set("budgets.{$mum->id}", '-5')
        ->assertHasErrors("budgets.{$mum->id}")
        ->assertSee(__('validation.min.numeric', ['attribute' => "budgets.{$mum->id}", 'min' => 0]));
});
