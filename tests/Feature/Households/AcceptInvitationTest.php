<?php

declare(strict_types=1);

use App\Actions\Households\AcceptHouseholdInvitation;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function invitation(array $attributes = [], string $token = 'secret-token'): HouseholdInvitation
{
    return HouseholdInvitation::factory()->create([
        'household_id' => Household::factory()->create(['name' => 'Evans household'])->id,
        'email' => 'sam@example.com',
        'token_hash' => hash('sha256', $token),
        ...$attributes,
    ]);
}

function registerFromInvite(string $email = 'sam@example.com', string $token = 'secret-token')
{
    return test()->post(route('register.store'), [
        'name' => 'Sam',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation' => $token,
    ]);
}

it('shows the invited email and household name on the registration page', function () {
    invitation();

    $this->get(route('register', ['invitation' => 'secret-token']))
        ->assertOk()
        ->assertSee('sam@example.com')
        ->assertSee('joining Evans household')
        ->assertSee('name="invitation"', false);
});

it('adds a user who registers from an invite to the inviter\'s household', function () {
    $invitation = invitation();

    registerFromInvite()->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'sam@example.com')->household_id)->toBe($invitation->household_id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('does not create a new household for an invited user', function () {
    invitation();
    $before = Household::count();

    registerFromInvite();

    expect(Household::count())->toBe($before);
});

it('marks an invited user\'s email as verified without sending a verification email', function () {
    Notification::fake();
    invitation();

    registerFromInvite();

    expect(User::firstWhere('email', 'sam@example.com')->hasVerifiedEmail())->toBeTrue();
    Notification::assertNothingSent();
});

it('rejects an invite that has expired, been revoked or already been used', function (string $field, mixed $value) {
    invitation([$field => $value]);

    registerFromInvite()->assertSessionHasErrors(['email' => 'This invite link is no longer valid.']);

    expect(User::count())->toBe(0);
})->with([
    'expired' => ['expires_at', fn () => now()->subMinute()],
    'revoked' => ['revoked_at', fn () => now()],
    'used' => ['accepted_at', fn () => now()],
]);

it('rejects registering from an invite with a different email address', function () {
    invitation();

    registerFromInvite('other@example.com')->assertSessionHasErrors(['email' => 'Use the email address the invite was sent to.']);

    expect(User::count())->toBe(0);
});

it('redirects an invalid invite link to registration with an error', function () {
    $this->get('/invitations/nope')
        ->assertRedirect(route('register'))
        ->assertSessionHas('error', 'This invite link is no longer valid.');
});

it('redirects a valid invite link to registration with the invitation', function () {
    invitation();

    $this->get(route('invitations.show', 'secret-token'))
        ->assertRedirect(route('register', ['invitation' => 'secret-token']));
});

it('does not accept an invitation that was used between lookup and accept', function () {
    $invitation = invitation(['accepted_at' => now()]);

    expect(fn () => (new AcceptHouseholdInvitation)->handle($invitation))->toThrow(ValidationException::class);
    expect(User::count())->toBe(0);
});
