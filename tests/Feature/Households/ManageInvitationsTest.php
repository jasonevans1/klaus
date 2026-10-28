<?php

declare(strict_types=1);

use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Notifications\HouseholdInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('revokes a pending invitation', function () {
    $me = User::factory()->create();
    $invitation = HouseholdInvitation::factory()->create(['household_id' => $me->household_id]);
    $this->actingAs($me);

    Livewire::test('pages::settings.household')->call('revoke', $invitation->id);

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});

it('removes a revoked invitation from the pending list', function () {
    $me = User::factory()->create();
    $invitation = HouseholdInvitation::factory()->create(['household_id' => $me->household_id, 'email' => 'gone@example.com']);
    $this->actingAs($me);

    Livewire::test('pages::settings.household')
        ->assertSee('gone@example.com')
        ->call('revoke', $invitation->id)
        ->assertDontSee('gone@example.com');
});

it('re-sends an invitation with a new link and a new expiry date', function () {
    Notification::fake();
    $this->travelTo(now()->startOfSecond());
    $me = User::factory()->create();
    $old = HouseholdInvitation::factory()->create([
        'household_id' => $me->household_id,
        'email' => 'pending@example.com',
        'expires_at' => now()->addDay(),
    ]);
    $this->actingAs($me);

    Livewire::test('pages::settings.household')->call('resend', $old->id);

    $new = $me->household->pendingInvitations()->where('email', 'pending@example.com')->sole();
    expect($new->id)->not->toBe($old->id)
        ->and($new->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
    Notification::assertSentOnDemand(HouseholdInvitationNotification::class);
});

it('stops the old link from working after a re-send', function () {
    Notification::fake();
    $me = User::factory()->create();
    $token = 'old-token';
    $old = HouseholdInvitation::factory()->create([
        'household_id' => $me->household_id,
        'token_hash' => hash('sha256', $token),
    ]);
    $this->actingAs($me);
    expect(HouseholdInvitation::findPendingByToken($token))->not->toBeNull();

    Livewire::test('pages::settings.household')->call('resend', $old->id);

    expect(HouseholdInvitation::findPendingByToken($token))->toBeNull();
});

it('cannot revoke or re-send another household\'s invitation', function () {
    Notification::fake();
    $me = User::factory()->create();
    $other = HouseholdInvitation::factory()->create();
    $this->actingAs($me);

    Livewire::test('pages::settings.household')->call('revoke', $other->id)->assertNotFound();
    Livewire::test('pages::settings.household')->call('resend', $other->id)->assertNotFound();

    expect($other->fresh()->revoked_at)->toBeNull();
    Notification::assertNothingSent();
});
