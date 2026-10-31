<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\Occasion;
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

    /**
     * Start a new Christmas for $year from the Occasions page and report what I was told.
     */
    public function startsChristmas(int $year): ChristmasAttempt
    {
        $page = Livewire::actingAs($this->user)
            ->test('pages::occasions.index')
            ->set('year', $year)
            ->call('createChristmas');

        $refusal = $page->errors()->first('year');

        auth()->logout();

        return new ChristmasAttempt($refusal === '' ? null : $refusal);
    }

    /**
     * The names of my household's occasions, in the order the Occasions page lists them.
     *
     * @return list<string>
     */
    public function occasions(): array
    {
        $html = Livewire::actingAs($this->user)->test('pages::occasions.index')->html();

        auth()->logout();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

        $names = [];
        foreach ((new DOMXPath($dom))->query('//*[@data-occasion]') as $row) {
            $names[] = $row->getAttribute('data-occasion');
        }

        return $names;
    }

    /**
     * The names of the people the "Add people" window offers for the occasion, in the order shown.
     *
     * @return list<string>
     */
    public function peopleICanAddTo(string $occasion): array
    {
        $html = $this->addPeoplePage($occasion)->html();

        auth()->logout();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);

        $names = [];
        foreach ((new DOMXPath($dom))->query('//*[@data-available]') as $row) {
            $names[] = $row->getAttribute('data-available');
        }

        return $names;
    }

    /**
     * Put the named people on the occasion by ticking them in "Add people".
     */
    public function addsTo(string $occasion, string ...$names): void
    {
        $this->addPeoplePage($occasion)
            ->set('selected', array_map(fn (string $name) => (string) $this->personId($name), $names))
            ->call('addSelected')
            ->assertHasNoErrors();

        auth()->logout();
    }

    /**
     * Put everyone on the occasion with "Add all".
     */
    public function addsEveryoneTo(string $occasion): void
    {
        $this->addPeoplePage($occasion)->call('addAll')->assertHasNoErrors();

        auth()->logout();
    }

    private function addPeoplePage(string $occasion): Testable
    {
        return Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($occasion)])
            ->call('openAddPeople');
    }

    /**
     * Type a whole-dollar budget for the person on the occasion page; null empties the field (no budget).
     */
    public function setsBudget(string $occasion, string $name, ?int $dollars): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($occasion)])
            ->set('budgets.'.$this->personId($name), $dollars === null ? '' : (string) $dollars)
            ->assertHasNoErrors();

        auth()->logout();
    }

    /**
     * Remove the person from the occasion page.
     */
    public function removesFrom(string $occasion, string $name): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($occasion)])
            ->call('remove', $this->personId($name));

        auth()->logout();
    }

    /**
     * What the occasion page shows me: its people under their group headings with their budgets, and the total budget.
     */
    public function occasion(string $name): SeenOccasion
    {
        $html = Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($name)])
            ->html();

        auth()->logout();

        return SeenOccasion::read($html);
    }

    /**
     * Open the occasion page and leave it open, so I can click on it later (after my partner has changed things).
     */
    public function opens(string $occasion): OpenOccasionPage
    {
        $page = Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($occasion)]);

        auth()->logout();

        return new OpenOccasionPage($this, $page);
    }

    /**
     * Click the one-click button on the person's gift (Mark bought / Mark wrapped / Mark given) on the occasion page.
     */
    public function advancesGift(string $on, string $for, string $title): void
    {
        $this->opens($on)->advancesGift($for, $title);
    }

    /**
     * Double-click the one-click button on the person's gift: both clicks are sent from the page as it was.
     */
    public function doubleClicksAdvance(string $on, string $for, string $title): void
    {
        $this->opens($on)->advancesGift($for, $title, times: 2);
    }

    /**
     * Open the person's gift in the gift window, pick a status (Idea, Bought, Wrapped, Given) and who bought it, and save.
     * With no buyer the "Bought by" choice is left empty.
     */
    public function setsGiftStatus(string $on, string $for, string $title, string $status, ?string $by = null): void
    {
        $page = Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($on)])
            ->call('editGift', $this->giftId($for, $title));

        $this->entersStatus($page, $status, $by);

        $page->call('saveGift')->assertHasNoErrors();

        auth()->logout();
    }

    private function entersStatus(Testable $page, string $status, ?string $by): void
    {
        $buyerId = $by === null ? '' : (string) User::where('household_id', $this->user->household_id)->where('name', $by)->firstOrFail()->id;

        $page->set('giftForm.status', strtolower($status))->set('giftForm.buyerId', $buyerId);
    }

    /**
     * Add gifts for the person from the occasion page, typing them in one after another with
     * "Save and add another" and pressing Save after the last one.
     */
    public function addsGifts(string $on, string $for, GiftEntry ...$gifts): void
    {
        $page = Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($on)])
            ->call('addGift', $this->personId($for));

        foreach ($gifts as $i => $gift) {
            $page->set('giftForm.title', $gift->title)
                ->set('giftForm.price', $gift->price)
                ->set('giftForm.store', $gift->store)
                ->set('giftForm.url', $gift->link);

            if ($gift->status !== null) {
                $this->entersStatus($page, $gift->status, $gift->buyer);
            }

            $page->call($i === array_key_last($gifts) ? 'saveGift' : 'saveGiftAndAddAnother')
                ->assertHasNoErrors();
        }

        auth()->logout();
    }

    /**
     * Edit the person's gift called $title on the occasion page, replacing its details with $gift.
     */
    public function changesGift(string $on, string $for, string $title, GiftEntry $gift): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($on)])
            ->call('editGift', $this->giftId($for, $title))
            ->set('giftForm.title', $gift->title)
            ->set('giftForm.price', $gift->price)
            ->set('giftForm.store', $gift->store)
            ->set('giftForm.url', $gift->link)
            ->call('saveGift')
            ->assertHasNoErrors();

        auth()->logout();
    }

    /**
     * Delete the person's gift called $title from the occasion page.
     */
    public function deletesGift(string $on, string $for, string $title): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($on)])
            ->call('deleteGift', $this->giftId($for, $title));

        auth()->logout();
    }

    public function giftId(string $for, string $title): int
    {
        return Gift::where('household_id', $this->user->household_id)
            ->where('recipient_id', $this->personId($for))
            ->where('title', $title)
            ->firstOrFail()->id;
    }

    /**
     * The question I am asked before removing the person from the occasion.
     */
    public function warningWhenRemoving(string $name, string $from): string
    {
        $html = Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($from)])
            ->html();

        auth()->logout();

        return $this->confirmation($html, '//*[@data-person="'.$name.'"]//*[@data-test="remove-person-button"]');
    }

    /**
     * The question I am asked before deleting the occasion from the Occasions page.
     */
    public function warningWhenDeleting(string $occasion): string
    {
        $html = Livewire::actingAs($this->user)->test('pages::occasions.index')->html();

        auth()->logout();

        return $this->confirmation($html, '//*[@data-occasion="'.$occasion.'"]//*[@data-test="delete-occasion-button"]');
    }

    /**
     * Delete the occasion from the Occasions page.
     */
    public function deletesOccasion(string $occasion): void
    {
        Livewire::actingAs($this->user)->test('pages::occasions.index')->call('delete', $this->occasionId($occasion));

        auth()->logout();
    }

    private function confirmation(string $html, string $query): string
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $button = (new DOMXPath($dom))->query($query)->item(0);

        expect($button)->not->toBeNull('The page has no such button to confirm');

        return $button->getAttribute('wire:confirm');
    }

    private function occasionId(string $name): int
    {
        return Occasion::where('household_id', $this->user->household_id)->where('name', $name)->firstOrFail()->id;
    }

    public function personId(string $name): int
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

/**
 * The outcome of trying to start a Christmas: refusal is the message I was shown, or null when it was created.
 */
final class ChristmasAttempt
{
    public function __construct(public readonly ?string $refusal) {}
}

/**
 * An occasion page as I see it: group label => [person name => budget as shown, e.g. "$1,250" or "No budget"],
 * both in the order shown, plus the total budget as shown.
 */
final class SeenOccasion
{
    /**
     * @param  array<string, array<string, string>>  $groups
     * @param  array<string, list<array{string, string, string, string}>>  $gifts  person name => their gifts as shown,
     *                                                                             each [title, price, store, link], in the order shown
     * @param  array<string, list<array{title: string, status: string, buyer: string, button: string|null, text: string}>>  $giftProgress  person name => where each
     *                                                                                                                                     of their gifts has got to, in the order shown
     * @param  array<string, array{spent: string, remaining: string, text: string}>  $spending  person name => spent and remaining as shown
     *                                                                                          (remaining "" when they have no budget), and their row's text
     * @param  list<string>  $noGift  the names listed under "No gift yet", in the order shown
     */
    public function __construct(
        public readonly array $groups,
        public readonly string $totalBudget,
        public readonly array $gifts = [],
        public readonly array $giftProgress = [],
        public readonly array $spending = [],
        public readonly string $totalSpent = '',
        public readonly string $totalRemaining = '',
        public readonly array $noGift = [],
        public readonly bool $showsNoGiftHeading = false,
    ) {}

    /**
     * Read the occasion page as it is shown.
     */
    public static function read(string $html): self
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $groups = [];
        foreach ($xpath->query('//*[@data-group]') as $group) {
            $label = $group->getAttribute('data-group');

            foreach ($xpath->query('.//*[@data-person]', $group) as $row) {
                $groups[$label][$row->getAttribute('data-person')] = $row->getAttribute('data-budget');
            }
        }

        $total = $xpath->query('//*[@data-total-budget]')->item(0);
        expect($total)->not->toBeNull('The occasion page shows no total budget');

        // Each person's gifts are listed in the keyed block that holds the person's row.
        $gifts = [];
        foreach ($xpath->query('//*[@data-person]') as $row) {
            $gifts[$row->getAttribute('data-person')] = [];
            $block = $xpath->query('ancestor::*[@*[name()="wire:key"]][1]', $row)->item(0);

            foreach ($block === null ? [] : $xpath->query('.//*[@data-gift]', $block) as $gift) {
                $gifts[$row->getAttribute('data-person')][] = [
                    $gift->getAttribute('data-gift'),
                    $gift->getAttribute('data-price'),
                    $gift->getAttribute('data-store'),
                    $gift->getAttribute('data-url'),
                ];
            }
        }

        // Where each gift has got to: its status, who bought it and the one-click button on its row.
        $progress = [];
        foreach ($xpath->query('//*[@data-person]') as $row) {
            $progress[$row->getAttribute('data-person')] = [];
            $block = $xpath->query('ancestor::*[@*[name()="wire:key"]][1]', $row)->item(0);

            foreach ($block === null ? [] : $xpath->query('.//*[@data-gift]', $block) as $gift) {
                $button = $xpath->query('.//*[@data-test="advance-gift-button"]', $gift)->item(0);

                $progress[$row->getAttribute('data-person')][] = [
                    'title' => $gift->getAttribute('data-gift'),
                    'status' => $gift->getAttribute('data-status'),
                    'buyer' => $gift->getAttribute('data-buyer'),
                    'button' => $button === null ? null : trim((string) preg_replace('/\s+/', ' ', $button->textContent)),
                    'text' => trim((string) preg_replace('/\s+/', ' ', $gift->textContent)),
                ];
            }
        }

        // What has been spent on each person and what is left of their budget, as their row shows it.
        $spending = [];
        foreach ($xpath->query('//*[@data-person]') as $row) {
            $spending[$row->getAttribute('data-person')] = [
                'spent' => $row->getAttribute('data-spent'),
                'remaining' => $row->getAttribute('data-remaining'),
                'text' => trim((string) preg_replace('/\s+/', ' ', $row->textContent)),
            ];
        }

        $totalSpent = $xpath->query('//*[@data-total-spent]')->item(0);
        $totalRemaining = $xpath->query('//*[@data-total-remaining]')->item(0);

        $noGift = [];
        foreach ($xpath->query('//*[@data-no-gift]') as $row) {
            $noGift[] = $row->getAttribute('data-no-gift');
        }

        return new self(
            $groups,
            $total->getAttribute('data-total-budget'),
            $gifts,
            $progress,
            $spending,
            $totalSpent?->getAttribute('data-total-spent') ?? '',
            $totalRemaining?->getAttribute('data-total-remaining') ?? '',
            $noGift,
            str_contains((string) preg_replace('/\s+/', ' ', $dom->textContent), 'No gift yet'),
        );
    }
}

/**
 * An occasion page I opened earlier and left open; what I click on it is sent from the page as I last saw it.
 */
final class OpenOccasionPage
{
    public function __construct(
        public readonly Member $member,
        public readonly Testable $page,
    ) {}

    /**
     * Click the one-click button on the person's gift as this page shows it, $times times in a row.
     */
    public function advancesGift(string $for, string $title, int $times = 1): void
    {
        $html = $this->page->html();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $row = $xpath->query('//*[@data-person="'.$for.'"]/following-sibling::*//*[@data-gift="'.$title.'"]')->item(0);
        $button = $row === null ? null : $xpath->query('.//*[@data-test="advance-gift-button"]', $row)->item(0);
        expect($button)->not->toBeNull("{$title} has no advance button on the page");

        expect(preg_match('/^(\w+)\((.*)\)$/', $button->getAttribute('wire:click'), $call))->toBe(1);
        $params = json_decode('['.str_replace("'", '"', $call[2]).']', true);

        Livewire::actingAs($this->member->user);
        for ($i = 0; $i < $times; $i++) {
            $this->page->call($call[1], ...$params);
        }

        auth()->logout();
    }

    /**
     * Type a whole-dollar budget for the person on this page; null empties the field (no budget).
     */
    public function setsBudget(string $for, ?int $dollars): void
    {
        Livewire::actingAs($this->member->user);
        $this->page->set('budgets.'.$this->member->personId($for), $dollars === null ? '' : (string) $dollars)->assertHasNoErrors();

        auth()->logout();
    }

    /**
     * Delete the person's gift called $title from this page.
     */
    public function deletesGift(string $for, string $title): void
    {
        Livewire::actingAs($this->member->user);
        $this->page->call('deleteGift', $this->member->giftId($for, $title));

        auth()->logout();
    }

    /**
     * Remove the person from the occasion on this page.
     */
    public function removes(string $name): void
    {
        Livewire::actingAs($this->member->user);
        $this->page->call('remove', $this->member->personId($name));

        auth()->logout();
    }

    /**
     * What this page shows me now, without reloading it.
     */
    public function shows(): SeenOccasion
    {
        return SeenOccasion::read($this->page->html());
    }
}

/**
 * A gift as I would type it in: title, price in dollars and cents (e.g. "24.99"), store and product link, all but the title optional.
 */
final class GiftEntry
{
    public string $price = '';

    public string $store = '';

    public string $link = '';

    public ?string $status = null;

    public ?string $buyer = null;

    public function __construct(public readonly string $title) {}

    /**
     * Enter the gift with a status other than Idea (e.g. it is already bought) and who bought it.
     */
    public function thatIs(string $status, ?string $by = null): self
    {
        [$this->status, $this->buyer] = [$status, $by];

        return $this;
    }

    public function costing(string $dollars): self
    {
        $this->price = $dollars;

        return $this;
    }

    public function from(string $store): self
    {
        $this->store = $store;

        return $this;
    }

    public function at(string $link): self
    {
        $this->link = $link;

        return $this;
    }
}

function gift(string $title): GiftEntry
{
    return new GiftEntry($title);
}
