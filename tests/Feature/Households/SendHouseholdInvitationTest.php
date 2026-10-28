<?php

use App\Actions\Households\SendHouseholdInvitation;
use App\Models\User;
use App\Notifications\HouseholdInvitationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    $this->inviter = User::factory()->create();
    $this->send = fn (string $email = 'Friend@Example.com') => app(SendHouseholdInvitation::class)->handle($this->inviter, $email);
});

it('creates a pending invitation for the inviter\'s household', function () {
    $invitation = ($this->send)();

    expect($invitation->household_id)->toBe($this->inviter->household_id)
        ->and($invitation->email)->toBe('friend@example.com')
        ->and($invitation->invited_by_user_id)->toBe($this->inviter->id)
        ->and($this->inviter->household->pendingInvitations()->pluck('id')->all())->toBe([$invitation->id]);
});

it('stores only a hash of the invitation token', function () {
    $invitation = ($this->send)();

    Notification::assertSentOnDemand(HouseholdInvitationNotification::class, function ($n) use ($invitation) {
        return $n->token !== $invitation->token_hash && hash('sha256', $n->token) === $invitation->token_hash;
    });
});

it('expires the invitation after seven days', function () {
    $this->freezeTime();
    $invitation = ($this->send)();

    expect($invitation->expires_at->timestamp)->toBe(now()->addDays(7)->timestamp);
});

it('emails the invitee a link containing the token', function () {
    ($this->send)();

    Notification::assertSentOnDemand(HouseholdInvitationNotification::class, function ($n, $channels, $notifiable) {
        $mail = $n->toMail($notifiable);

        return $notifiable->routes['mail'] === 'friend@example.com'
            && $mail->actionUrl === url('/invitations/'.$n->token)
            && $mail->subject === "{$this->inviter->name} invited you to Klaus";
    });
});

it('rejects an email that already has a Klaus account', function () {
    User::factory()->create(['email' => 'friend@example.com']);

    expect(fn () => ($this->send)('FRIEND@example.com'))->toThrow(ValidationException::class, 'That email already has a Klaus account.');
    Notification::assertNothingSent();
});

it('replaces an earlier pending invitation to the same email', function () {
    $first = ($this->send)();
    $second = ($this->send)();

    expect($first->fresh()->revoked_at)->not->toBeNull()
        ->and($this->inviter->household->pendingInvitations()->pluck('id')->all())->toBe([$second->id]);
});

it('sends the invitation email without queueing it', function () {
    expect(is_subclass_of(HouseholdInvitationNotification::class, ShouldQueue::class))->toBeFalse();
});

it('rate limits how many invites one member can send', function () {
    RateLimiter::clear('household-invite:'.$this->inviter->id);

    foreach (range(1, 10) as $i) {
        ($this->send)("friend{$i}@example.com");
    }

    expect(fn () => ($this->send)('one-more@example.com'))->toThrow(ValidationException::class, 'Too many invites sent. Try again later.');
});

it('keeps the earlier invitation valid when sending the email fails', function () {
    $first = ($this->send)();

    app()->forgetInstance(ChannelManager::class);
    Notification::clearResolvedInstance(ChannelManager::class);
    Event::listen(NotificationSending::class, fn () => throw new RuntimeException('mail down'));

    expect(fn () => ($this->send)())->toThrow(RuntimeException::class);
    expect($first->fresh()->revoked_at)->toBeNull()
        ->and($this->inviter->household->invitations()->count())->toBe(1);
});
