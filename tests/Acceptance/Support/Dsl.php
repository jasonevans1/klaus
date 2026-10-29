<?php

declare(strict_types=1);

use App\Models\Recipient;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Livewire\Features\SupportTesting\Testable;
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

    /**
     * Add a person on the People page (one at a time, as when copying from last year's spreadsheet).
     */
    public function adds(PersonEntry $person): void
    {
        $page = Livewire::actingAs($this->user)->test('pages::people.index')->call('create');

        $this->saveEntry($page, $person);
    }

    /**
     * Edit the person named $name on the People page, replacing their details with $person.
     */
    public function changes(string $name, PersonEntry $person): void
    {
        $page = Livewire::actingAs($this->user)->test('pages::people.index')->call('edit', $this->personId($name));

        $this->saveEntry($page, $person);
    }

    private function saveEntry(Testable $page, PersonEntry $person): void
    {
        $page->set('form.name', $person->name)
            ->set('form.group', strtolower($person->group))
            ->set('form.is_shared', $person->shared)
            ->set('form.birthday_month', (string) $person->birthdayMonth)
            ->set('form.birthday_day', (string) $person->birthdayDay)
            ->set('form.birthday_year', (string) $person->birthdayYear)
            ->set('form.interests', $person->interests)
            ->call('save')
            ->assertHasNoErrors();

        auth()->logout();
    }

    /**
     * Archive the person named $name from the People page.
     */
    public function archives(string $name): void
    {
        $page = Livewire::actingAs($this->user)->test('pages::people.index');
        $page->call('archive', $this->personId($name));

        auth()->logout();
    }

    /**
     * Restore the archived person named $name: turn on "Show archived" on the People page and restore them.
     */
    public function restores(string $name): void
    {
        $page = Livewire::actingAs($this->user)->test('pages::people.index')->set('showArchived', true);
        $page->call('restore', $this->personId($name));

        auth()->logout();
    }

    /**
     * What the People page shows me: my household's people under their group headings, without archived people.
     */
    public function peopleList(): SeenPeople
    {
        return $this->readPeople(false);
    }

    /**
     * What the People page shows me with "Show archived" turned on.
     */
    public function peopleListIncludingArchived(): SeenPeople
    {
        return $this->readPeople(true);
    }

    private function personId(string $name): int
    {
        return Recipient::where('household_id', $this->user->household_id)->where('name', $name)->firstOrFail()->id;
    }

    private function readPeople(bool $includeArchived): SeenPeople
    {
        $html = Livewire::actingAs($this->user)
            ->test('pages::people.index')
            ->set('showArchived', $includeArchived)
            ->html();

        auth()->logout();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $groups = [];
        foreach ($xpath->query('//*[@data-group]') as $group) {
            $label = trim((string) $xpath->evaluate('string(.//*[@data-flux-heading][1])', $group));
            $label = trim((string) preg_replace('/\s*\(\d+\)$/', '', $label));

            foreach ($xpath->query('.//*[@data-person]', $group) as $row) {
                $age = $row->getAttribute('data-age');
                $groups[$label][] = new SeenPerson(
                    $row->getAttribute('data-person'),
                    $row->hasAttribute('data-shared'),
                    $row->hasAttribute('data-birthday') ? $row->getAttribute('data-birthday') : null,
                    $age === '' ? null : (int) $age,
                    $row->hasAttribute('data-archived'),
                );
            }
        }

        return new SeenPeople($groups);
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

/**
 * The details of a person as I would type them in: name, group (Family, Friends, Teachers, Coworkers),
 * birthday (month and day, year optional), whether it is a shared entry like "The Smiths", and interests.
 */
final class PersonEntry
{
    public string $group = 'Family';

    public bool $shared = false;

    public ?int $birthdayMonth = null;

    public ?int $birthdayDay = null;

    public ?int $birthdayYear = null;

    public string $interests = '';

    public function __construct(public readonly string $name) {}

    public function in(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function shared(): self
    {
        $this->shared = true;

        return $this;
    }

    public function born(int $month, int $day, ?int $year = null): self
    {
        [$this->birthdayMonth, $this->birthdayDay, $this->birthdayYear] = [$month, $day, $year];

        return $this;
    }

    public function interests(string $interests): self
    {
        $this->interests = $interests;

        return $this;
    }
}

/**
 * One person as shown on the People page.
 */
final class SeenPerson
{
    public function __construct(
        public readonly string $name,
        public readonly bool $shared,
        public readonly ?string $birthday,
        public readonly ?int $age,
        public readonly bool $archived,
    ) {}
}

/**
 * The People page as I see it: group label => the people listed under it, both in the order shown.
 */
final class SeenPeople
{
    /**
     * @param  array<string, list<SeenPerson>>  $groups
     */
    public function __construct(public readonly array $groups) {}

    /**
     * @return array<string, list<string>>
     */
    public function names(): array
    {
        return array_map(fn (array $people) => array_map(fn (SeenPerson $p) => $p->name, $people), $this->groups);
    }

    /**
     * @return list<SeenPerson>
     */
    public function everyone(): array
    {
        return array_merge([], ...array_values($this->groups));
    }

    public function person(string $name): SeenPerson
    {
        $found = array_values(array_filter($this->everyone(), fn (SeenPerson $p) => $p->name === $name));

        expect($found)->toHaveCount(1, "Expected exactly one person named {$name} on the People page");

        return $found[0];
    }
}

function person(string $name): PersonEntry
{
    return new PersonEntry($name);
}

/**
 * Fix today's date so birthdays and ages are predictable.
 */
function itIs(string $date): void
{
    test()->travelTo(Carbon\Carbon::parse($date));
}
