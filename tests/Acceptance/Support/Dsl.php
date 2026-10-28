<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Livewire\Livewire;

/**
 * Someone who uses Klaus. Everyone in the scenarios has the password "password".
 */
final class Member
{
    public function __construct(public readonly User $user) {}

    /**
     * Invite someone by email from the Household settings page and report what I was told.
     */
    public function invites(string $email): InviteAttempt
    {
        mailbox();

        $page = Livewire::actingAs($this->user)
            ->test('pages::settings.household')
            ->set('form.email', $email)
            ->call('invite');

        $refusal = $page->errors()->first('form.email');

        // Livewire::actingAs leaves me logged in; the next person in the scenario arrives as a guest.
        auth()->logout();

        return new InviteAttempt($refusal === '' ? null : $refusal);
    }
}

/**
 * The outcome of trying to invite someone: refusal is the message I was shown, or null when the invite went out.
 */
final class InviteAttempt
{
    public function __construct(public readonly ?string $refusal) {}
}

/**
 * The household a member sees after logging in: its name and the names of its members.
 */
final class SeenHousehold
{
    /**
     * @param  list<string>  $members
     */
    public function __construct(
        public readonly string $name,
        public readonly array $members,
    ) {}
}

/**
 * The scenarios read the mail Klaus sends, so mail never leaves the test.
 */
function mailbox(): NotificationFake
{
    $fake = Notification::getFacadeRoot();

    if (! $fake instanceof NotificationFake) {
        $fake = Notification::fake();
    }

    return $fake;
}

/**
 * Someone signs up through open registration (no invite), confirms their email, and logs out.
 */
function signsUp(string $name, string $email): Member
{
    mailbox();

    test()->post(route('register.store'), [
        'name' => $name,
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', $email)->firstOrFail();

    test()->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]));

    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();

    test()->post(route('logout'));
    test()->assertGuest();

    return new Member($user->fresh() ?? $user);
}

/**
 * The invitee follows the link in the invite email sent to $to and signs up with that email.
 */
function acceptsInvite(string $to, string $name): Member
{
    $join = null;

    foreach (mailbox()->sentNotifications()[AnonymousNotifiable::class] ?? [] as $byKey) {
        foreach ($byKey as $sends) {
            foreach ($sends as $send) {
                $route = $send['notifiable']->routes['mail'] ?? null;
                $addresses = is_array($route) ? array_keys($route) : [$route];

                if (in_array(strtolower($to), array_map(fn ($a) => strtolower((string) $a), $addresses), true)) {
                    $join = $send['notification']->toMail($send['notifiable'])->actionUrl;
                }
            }
        }
    }

    expect($join)->not->toBeNull("No invite email was sent to {$to}");

    test()->get($join)->assertRedirect();
    test()->followingRedirects()->get($join)->assertSee($to);

    $token = basename((string) parse_url((string) $join, PHP_URL_PATH));

    test()->post(route('register.store'), [
        'name' => $name,
        'email' => $to,
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation' => $token,
    ])->assertSessionHasNoErrors();

    test()->post(route('logout'));
    test()->assertGuest();

    return new Member(User::where('email', $to)->firstOrFail());
}

/**
 * Logs in as the member and reads the household name and member names shown on their dashboard.
 */
function householdOf(Member $member): SeenHousehold
{
    test()->post(route('login.store'), ['email' => $member->user->email, 'password' => 'password']);

    $html = test()->get(route('dashboard'))->assertOk()->getContent();

    test()->post(route('logout'));
    test()->assertGuest();

    preg_match('/data-household-name[^>]*>\s*(.*?)\s*</s', (string) $html, $name);
    preg_match('/data-household-members[^>]*>(.*?)</s', (string) $html, $members);

    expect($name)->not->toBeEmpty('The dashboard shows no household name');

    return new SeenHousehold(
        html_entity_decode($name[1], ENT_QUOTES),
        array_map(html_entity_decode(...), explode(', ', $members[1] ?? '')),
    );
}

/**
 * How many emails Klaus sent to an address that has no account behind it (e.g. invites).
 */
function invitesSentTo(string $email): int
{
    $count = 0;

    foreach (mailbox()->sentNotifications()[AnonymousNotifiable::class] ?? [] as $byKey) {
        foreach ($byKey as $sends) {
            foreach ($sends as $send) {
                $route = $send['notifiable']->routes['mail'] ?? null;
                $addresses = is_array($route) ? array_keys($route) : [$route];

                if (in_array(strtolower($email), array_map(fn ($a) => strtolower((string) $a), $addresses), true)) {
                    $count++;
                }
            }
        }
    }

    return $count;
}
