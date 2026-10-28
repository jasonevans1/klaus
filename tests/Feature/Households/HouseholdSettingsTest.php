<?php

declare(strict_types=1);

use App\Models\HouseholdInvitation;
use App\Models\User;
use Livewire\Livewire;

it('lists the members of my household', function () {
    $me = User::factory()->create(['name' => 'Jason Mine']);
    User::factory()->create(['name' => 'Partner Pat', 'household_id' => $me->household_id]);

    $this->actingAs($me);

    Livewire::test('pages::settings.household')
        ->assertSee('Jason Mine')
        ->assertSee('Partner Pat');
});

it('does not show members of another household', function () {
    $me = User::factory()->create();
    User::factory()->create(['name' => 'Stranger Sam']);

    $this->actingAs($me);

    Livewire::test('pages::settings.household')->assertDontSee('Stranger Sam');
});

it('lists pending invitations with their expiry date', function () {
    $me = User::factory()->create();
    HouseholdInvitation::factory()->create([
        'household_id' => $me->household_id,
        'email' => 'pending@example.com',
        'expires_at' => now()->addDays(3),
    ]);
    HouseholdInvitation::factory()->create([
        'household_id' => $me->household_id,
        'email' => 'revoked@example.com',
        'revoked_at' => now(),
    ]);

    $this->actingAs($me);

    Livewire::test('pages::settings.household')
        ->assertSee('pending@example.com')
        ->assertSee(now()->addDays(3)->format('M j, Y'))
        ->assertDontSee('revoked@example.com');
});

it('sends an invite from the invite form', function () {
    Notification::fake();
    $me = User::factory()->create();
    $this->actingAs($me);

    Livewire::test('pages::settings.household')
        ->set('form.email', 'new@example.com')
        ->call('invite')
        ->assertHasNoErrors()
        ->assertSet('form.email', '')
        ->assertSee('new@example.com');

    expect($me->household->pendingInvitations()->where('email', 'new@example.com')->exists())->toBeTrue();
});

it('shows an error when inviting an email that already has an account', function () {
    Notification::fake();
    $me = User::factory()->create();
    User::factory()->create(['email' => 'taken@example.com']);
    $this->actingAs($me);

    Livewire::test('pages::settings.household')
        ->set('form.email', 'taken@example.com')
        ->call('invite')
        ->assertHasErrors('form.email')
        ->assertSee('That email already has a Klaus account.');
});

it('shows an error when the invite email cannot be sent', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.household')
        ->set('form.email', 'new@example.com')
        ->call('invite')
        ->assertHasErrors('form.email')
        ->assertSee("Couldn't send the invite email. Try again.");
});

it('renames my household', function () {
    $me = User::factory()->create();
    $this->actingAs($me);

    Livewire::test('pages::settings.household')
        ->set('householdName', 'The Joneses')
        ->call('rename')
        ->assertHasNoErrors();

    expect($me->household->fresh()->name)->toBe('The Joneses');

    Livewire::test('pages::settings.household')
        ->set('householdName', '')
        ->call('rename')
        ->assertHasErrors('householdName');
});

it('requires a verified email to view the household page', function () {
    $this->actingAs(User::factory()->unverified()->create());

    $this->get(route('household.edit'))->assertRedirect(route('verification.notice'));
});
