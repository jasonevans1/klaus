<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use Livewire\Exceptions\ComponentNotFoundException;
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
     * Open "New Christmas" on the Occasions page and click Create without touching the year:
     * reports the year the window had filled in, the note saying what it copies, and any refusal.
     */
    public function setsUpNextChristmas(): NewChristmasDialog
    {
        $page = Livewire::actingAs($this->user)->test('pages::occasions.index');

        $year = $page->get('year');

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$page->html());
        $note = (new DOMXPath($dom))->query('//*[@data-copies-from]')->item(0);

        $page->call('createChristmas');
        $refusal = $page->errors()->first('year');

        auth()->logout();

        return new NewChristmasDialog(
            $year,
            $note === null ? null : trim((string) preg_replace('/\s+/', ' ', $note->textContent)),
            $refusal === '' ? null : $refusal,
        );
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
                ->set('giftForm.url', $gift->link)
                ->set('giftForm.isPrivate', $gift->private);

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

    /**
     * What the occasion's shopping list shows me: every gift still to buy, for everyone, and how many and how much.
     */
    public function shoppingList(string $occasion): SeenShoppingList
    {
        $html = Livewire::actingAs($this->user)
            ->test('pages::occasions.shopping', ['occasion' => $this->occasionId($occasion)])
            ->html();

        auth()->logout();

        return SeenShoppingList::read($html);
    }

    /**
     * Open the occasion's shopping list and leave it open, so I can tick things off as I shop.
     */
    public function opensShoppingList(string $occasion): OpenShoppingList
    {
        $page = Livewire::actingAs($this->user)
            ->test('pages::occasions.shopping', ['occasion' => $this->occasionId($occasion)]);

        auth()->logout();

        return new OpenShoppingList($this, $page);
    }

    /**
     * What the occasion's wrapping checklist shows me: every bought or wrapped gift, for everyone, and how many are wrapped.
     */
    public function wrappingChecklist(string $occasion): SeenWrappingChecklist
    {
        $html = Livewire::actingAs($this->user)
            ->test('pages::occasions.wrapping', ['occasion' => $this->occasionId($occasion)])
            ->html();

        auth()->logout();

        return SeenWrappingChecklist::read($html);
    }

    /**
     * Open the occasion's wrapping checklist and leave it open, so I can tick gifts off as I wrap them.
     */
    public function opensWrappingChecklist(string $occasion): OpenWrappingChecklist
    {
        $page = Livewire::actingAs($this->user)
            ->test('pages::occasions.wrapping', ['occasion' => $this->occasionId($occasion)]);

        auth()->logout();

        return new OpenWrappingChecklist($this, $page);
    }

    /**
     * Add ideas for the person on the Ideas page, with no occasion, typing them in one after another with
     * "Save and add another" and pressing Save after the last one.
     */
    public function addsIdeas(string $for, GiftEntry ...$ideas): void
    {
        $page = Livewire::actingAs($this->user)
            ->test('pages::ideas.index')
            ->call('addIdea', $this->personId($for));

        foreach ($ideas as $i => $idea) {
            $page->set('form.title', $idea->title)
                ->set('form.price', $idea->price)
                ->set('form.store', $idea->store)
                ->set('form.url', $idea->link)
                ->set('form.isPrivate', $idea->private)
                ->call($i === array_key_last($ideas) ? 'saveIdea' : 'saveIdeaAndAddAnother')
                ->assertHasNoErrors();
        }

        auth()->logout();
    }

    /**
     * What the Ideas page shows me: every active person with their year-round ideas.
     */
    public function ideaList(): SeenIdeaList
    {
        $html = Livewire::actingAs($this->user)->test('pages::ideas.index')->html();

        auth()->logout();

        return SeenIdeaList::read($html);
    }

    /**
     * What the person's own page shows me: their profile, their year-round ideas and what they got on each occasion, by year.
     */
    public function personPage(string $name): SeenPersonPage
    {
        try {
            $html = Livewire::actingAs($this->user)
                ->test('pages::people.show', ['person' => $this->personId($name)])
                ->html();
        } catch (ComponentNotFoundException) {
            throw new LogicException('Not implemented yet: the person page, with profile and ideas (task 002) and history (task 003)');
        } finally {
            auth()->logout();
        }

        return SeenPersonPage::read($html);
    }

    /**
     * Log in and read the reminders on the dashboard where login lands: upcoming birthdays and occasions with people who have no gift yet.
     */
    public function dashboard(): SeenDashboard
    {
        test()->post(route('login.store'), ['email' => $this->user->email, 'password' => 'password']);

        $html = (string) test()->get(route('dashboard'))->assertOk()->getContent();

        test()->post(route('logout'));
        test()->assertGuest();

        return SeenDashboard::read($html);
    }

    /**
     * Click "Add to this occasion" on the person's year-round idea called $title on the occasion page.
     */
    public function addsIdeaTo(string $occasion, string $for, string $title): void
    {
        $page = Livewire::actingAs($this->user)
            ->test('pages::occasions.show', ['occasion' => $this->occasionId($occasion)]);

        $page->call('assignIdea', $this->giftId($for, $title));

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

    /**
     * Log in, then open Klaus's front page and report the address I end up on.
     */
    public function opensKlaus(): string
    {
        test()->post(route('login.store'), ['email' => $this->user->email, 'password' => 'password']);

        $response = test()->get(route('home'));
        $landedOn = $response->isRedirect() ? (string) $response->headers->get('Location') : route('home');

        test()->post(route('logout'));
        test()->assertGuest();

        return $landedOn;
    }

    /**
     * Log in and read the logo and name at the top of the page login lands on.
     */
    public function homePage(): SeenBrand
    {
        test()->post(route('login.store'), ['email' => $this->user->email, 'password' => 'password']);

        $html = (string) test()->get(route('dashboard'))->assertOk()->getContent();

        test()->post(route('logout'));
        test()->assertGuest();

        return SeenBrand::read($html);
    }

    /**
     * How the People page lays out its lines: one box per group, and what each person's line offers at hand and under More.
     */
    public function peoplePage(bool $includingArchived = false): SeenListPage
    {
        return SeenListPage::read($this->listPage('People', $includingArchived)->html(), ['//*[@data-person]'], '//*[@data-person]');
    }

    /**
     * How the Ideas page lays out its lines: one box per group of people, each person with their ideas, and what each
     * person's line and each idea offers at hand and under More.
     */
    public function ideasPage(): SeenListPage
    {
        return SeenListPage::read($this->listPage('Ideas')->html(), ['//*[@data-person]', '//*[@data-idea]'], '//*[@data-person]');
    }

    /**
     * How the Occasions page lays out its lines: the box the occasions sit in, and what each occasion offers at hand and under More.
     */
    public function occasionsPage(): SeenListPage
    {
        return SeenListPage::read($this->listPage('Occasions')->html(), ['//*[@data-occasion]'], '//*[@data-occasion]');
    }

    /**
     * Open the ⋯ More menu on the line for $for on the People, Ideas or Occasions page and pick $choice (e.g. "Archive").
     * On the People page "Show archived" is on, so archived people can be restored.
     */
    public function picksFromMore(string $choice, string $for, string $on): void
    {
        $page = $this->listPage($on, true);

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$page->html());
        $xpath = new DOMXPath($dom);

        $line = $xpath->query('//*[@'.SeenListPage::NAMED_BY[$on].'="'.$for.'"]')->item(0);
        expect($line)->not->toBeNull("The {$on} page has no line for {$for}");

        $item = $xpath->query('.//*[@data-flux-menu]//*[@*[name()="wire:click"]][normalize-space(.)="'.$choice.'"]', $line)->item(0);
        expect($item)->not->toBeNull("The line for {$for} on the {$on} page has no \"{$choice}\" under More");

        expect(preg_match('/^(\w+)\((.*)\)$/', $item->getAttribute('wire:click'), $call))->toBe(1);
        $params = json_decode('['.str_replace("'", '"', $call[2]).']', true);

        Livewire::actingAs($this->user);
        $page->call($call[1], ...$params)->assertHasNoErrors();

        auth()->logout();
    }

    private function listPage(string $name, bool $includingArchived = false): Testable
    {
        $page = Livewire::actingAs($this->user)->test(match ($name) {
            'People' => 'pages::people.index',
            'Ideas' => 'pages::ideas.index',
            'Occasions' => 'pages::occasions.index',
        });

        if ($name === 'People') {
            $page->set('showArchived', $includingArchived);
        }

        auth()->logout();

        return $page;
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
 * Klaus's daily job runs overnight, as the scheduler does once a day, creating the birthdays coming up.
 */
function theDailyJobRuns(): void
{
    if (! array_key_exists('birthdays:create', Artisan::all())) {
        throw new LogicException('Not implemented yet: the daily birthdays:create command (task 004)');
    }

    expect(Artisan::call('birthdays:create'))->toBe(0, 'The daily job failed');
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
     * @param  array<string, list<array{title: string, private: string}>>  $giftPrivacy  person name => each of their gifts
     *                                                                                   and whether it is marked private ("yes", "no", or "" when unmarked)
     * @param  string|null  $myPrivateSpent  the "My private gifts" spent amount as shown, or null when there is no such line
     * @param  string  $html  everything the page sends to my browser
     * @param  array<string, list<array{title: string, price: string, button: string|null}>>  $yearRoundIdeas  person name => the
     *                                                                                                         year-round ideas listed under them, with the text of each one's button
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
        public readonly array $giftPrivacy = [],
        public readonly ?string $myPrivateSpent = null,
        public readonly string $myPrivateText = '',
        public readonly string $html = '',
        public readonly array $yearRoundIdeas = [],
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

        // Whether each gift is marked as private.
        $privacy = [];
        foreach ($xpath->query('//*[@data-person]') as $row) {
            $privacy[$row->getAttribute('data-person')] = [];
            $block = $xpath->query('ancestor::*[@*[name()="wire:key"]][1]', $row)->item(0);

            foreach ($block === null ? [] : $xpath->query('.//*[@data-gift]', $block) as $gift) {
                $privacy[$row->getAttribute('data-person')][] = [
                    'title' => $gift->getAttribute('data-gift'),
                    'private' => $gift->getAttribute('data-private'),
                ];
            }
        }

        $myPrivate = $xpath->query('//*[@data-my-private-spent]')->item(0);

        // Year-round ideas (with no occasion) listed under each person, waiting to be added to this occasion.
        $yearRound = [];
        foreach ($xpath->query('//*[@data-person]') as $row) {
            $yearRound[$row->getAttribute('data-person')] = [];
            $block = $xpath->query('ancestor::*[@*[name()="wire:key"]][1]', $row)->item(0);

            foreach ($block === null ? [] : $xpath->query('.//*[@data-year-round-idea]', $block) as $idea) {
                $button = $xpath->query('.//*[@data-test="assign-idea-button"]', $idea)->item(0);

                $yearRound[$row->getAttribute('data-person')][] = [
                    'title' => $idea->getAttribute('data-year-round-idea'),
                    'price' => $idea->getAttribute('data-price'),
                    'button' => $button === null ? null : trim((string) preg_replace('/\s+/', ' ', $button->textContent)),
                ];
            }
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
            $privacy,
            $myPrivate?->getAttribute('data-my-private-spent'),
            $myPrivate === null ? '' : trim((string) preg_replace('/\s+/', ' ', $myPrivate->textContent)),
            $html,
            $yearRound,
        );
    }
}

/**
 * The Ideas page as I see it: person name => their year-round ideas, each [title, price, store, link], in the order shown,
 * plus whether each idea is marked private.
 */
final class SeenIdeaList
{
    /**
     * @param  array<string, list<array{string, string, string, string}>>  $ideas
     * @param  array<string, list<array{title: string, private: string, text: string}>>  $privacy
     */
    public function __construct(
        public readonly array $ideas,
        public readonly array $privacy,
        public readonly string $html,
    ) {}

    public static function read(string $html): self
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $ideas = $privacy = [];
        foreach ($xpath->query('//*[@data-person]') as $row) {
            $name = $row->getAttribute('data-person');
            [$ideas[$name], $privacy[$name]] = [[], []];

            // A person's ideas are in the keyed block that is (or holds) the person's row.
            $block = $xpath->query('ancestor-or-self::*[@*[name()="wire:key"]][1]', $row)->item(0);

            foreach ($block === null ? [] : $xpath->query('.//*[@data-idea]', $block) as $idea) {
                $ideas[$name][] = [
                    $idea->getAttribute('data-idea'),
                    $idea->getAttribute('data-price'),
                    $idea->getAttribute('data-store'),
                    $idea->getAttribute('data-url'),
                ];
                $privacy[$name][] = [
                    'title' => $idea->getAttribute('data-idea'),
                    'private' => $idea->getAttribute('data-private'),
                    'text' => trim((string) preg_replace('/\s+/', ' ', $idea->textContent)),
                ];
            }
        }

        return new self($ideas, $privacy, $html);
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

    public bool $private = false;

    public function __construct(public readonly string $title) {}

    /**
     * Enter the gift with a status other than Idea (e.g. it is already bought) and who bought it.
     */
    public function thatIs(string $status, ?string $by = null): self
    {
        [$this->status, $this->buyer] = [$status, $by];

        return $this;
    }

    /**
     * Enter the gift with "Private" ticked in the gift window, so only I can see it.
     */
    public function privately(): self
    {
        $this->private = true;

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

/**
 * A shopping list as I see it: each gift still to buy as [who it is for, title, price, store, link], in the order shown,
 * plus the header's count and total, the header's text, and whether it says there is nothing left to buy.
 */
final class SeenShoppingList
{
    /**
     * @param  list<array{string, string, string, string, string}>  $ideas
     * @param  list<array{for: string, title: string, private: string, text: string}>  $privacy  each row and whether it is marked private
     */
    public function __construct(
        public readonly array $ideas,
        public readonly string $count,
        public readonly string $total,
        public readonly string $header,
        public readonly bool $saysNothingLeft,
        public readonly array $privacy = [],
        public readonly string $html = '',
    ) {}

    public static function read(string $html): self
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $ideas = [];
        foreach ($xpath->query('//*[@data-idea]') as $row) {
            $ideas[] = [
                $row->getAttribute('data-for'),
                $row->getAttribute('data-idea'),
                $row->getAttribute('data-price'),
                $row->getAttribute('data-store'),
                $row->getAttribute('data-url'),
            ];
        }

        $count = $xpath->query('//*[@data-ideas-count]')->item(0);
        $total = $xpath->query('//*[@data-ideas-total]')->item(0);
        expect($count)->not->toBeNull('The shopping list shows no count of ideas left')
            ->and($total)->not->toBeNull('The shopping list shows no total of ideas left');

        return new self(
            $ideas,
            $count->getAttribute('data-ideas-count'),
            $total->getAttribute('data-ideas-total'),
            trim((string) preg_replace('/\s+/', ' ', $count->textContent)),
            str_contains((string) preg_replace('/\s+/', ' ', $dom->textContent), 'Nothing left to buy.'),
            listPrivacy($xpath, 'data-idea'),
            $html,
        );
    }
}

/**
 * A shopping list I opened earlier and left open; what I click on it is sent from the list as I last saw it.
 */
final class OpenShoppingList
{
    public function __construct(
        public readonly Member $member,
        public readonly Testable $page,
    ) {}

    /**
     * Click Mark bought on the person's gift as this list shows it.
     */
    public function marksBought(string $for, string $title): void
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$this->page->html());
        $xpath = new DOMXPath($dom);

        $row = $xpath->query('//*[@data-for="'.$for.'"][@data-idea="'.$title.'"]')->item(0);
        $button = $row === null ? null : $xpath->query('.//*[@data-test="mark-bought-button"]', $row)->item(0);
        expect($button)->not->toBeNull("{$title} has no Mark bought button on the list");

        expect(preg_match('/^(\w+)\((.*)\)$/', $button->getAttribute('wire:click'), $call))->toBe(1);
        $params = json_decode('['.str_replace("'", '"', $call[2]).']', true);

        Livewire::actingAs($this->member->user);
        $this->page->call($call[1], ...$params);

        auth()->logout();
    }

    /**
     * What this list shows me now, without reloading it.
     */
    public function shows(): SeenShoppingList
    {
        return SeenShoppingList::read($this->page->html());
    }
}

/**
 * A wrapping checklist as I see it: each gift as [who it is for, title, "wrapped" or "to wrap"], in the order shown,
 * plus the header's counts and text, and whether it says there is nothing to wrap yet.
 */
final class SeenWrappingChecklist
{
    /**
     * @param  list<array{string, string, string}>  $gifts
     * @param  list<array{for: string, title: string, private: string, text: string}>  $privacy  each row and whether it is marked private
     */
    public function __construct(
        public readonly array $gifts,
        public readonly string $wrappedCount,
        public readonly string $total,
        public readonly string $header,
        public readonly bool $saysNothingToWrap,
        public readonly array $privacy = [],
        public readonly string $html = '',
    ) {}

    public static function read(string $html): self
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $gifts = [];
        foreach ($xpath->query('//*[@data-wrap]') as $row) {
            $wrapped = $row->getAttribute('data-wrapped');
            $gifts[] = [
                $row->getAttribute('data-for'),
                $row->getAttribute('data-wrap'),
                match ($wrapped) {
                    'yes' => 'wrapped',
                    'no' => 'to wrap',
                    default => $wrapped,
                },
            ];
        }

        $wrappedCount = $xpath->query('//*[@data-wrapped-count]')->item(0);
        $total = $xpath->query('//*[@data-to-wrap-count]')->item(0);
        expect($wrappedCount)->not->toBeNull('The wrapping checklist shows no count of wrapped gifts')
            ->and($total)->not->toBeNull('The wrapping checklist shows no count of gifts to wrap');

        return new self(
            $gifts,
            $wrappedCount->getAttribute('data-wrapped-count'),
            $total->getAttribute('data-to-wrap-count'),
            trim((string) preg_replace('/\s+/', ' ', $wrappedCount->textContent)),
            str_contains((string) preg_replace('/\s+/', ' ', $dom->textContent), 'Nothing to wrap yet.'),
            listPrivacy($xpath, 'data-wrap'),
            $html,
        );
    }
}

/**
 * A wrapping checklist I opened earlier and left open; what I click on it is sent from the list as I last saw it.
 */
final class OpenWrappingChecklist
{
    public function __construct(
        public readonly Member $member,
        public readonly Testable $page,
    ) {
        // The list as it was when opened, for rows that later disappear from the page.
        $this->openedHtml = $page->html();
    }

    private string $openedHtml;

    /**
     * Tick the person's gift that this list shows unticked, $times clicks in a row (all sent from the list as it was).
     */
    public function ticks(string $for, string $title, int $times = 1): void
    {
        $this->click($for, $title, 'no', $times);
    }

    /**
     * Untick the person's gift that this list shows ticked (puts it back to bought).
     */
    public function unticks(string $for, string $title): void
    {
        $this->click($for, $title, 'yes', 1);
    }

    private function click(string $for, string $title, string $wrapped, int $times): void
    {
        // The row as the page shows it now; a row that has since gone from the page is still on the list as it was opened.
        $xpath = $row = null;
        foreach ([$this->page->html(), $this->openedHtml] as $html) {
            $dom = new DOMDocument;
            @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
            $xpath = new DOMXPath($dom);
            $row = $xpath->query('//*[@data-for="'.$for.'"][@data-wrap="'.$title.'"]')->item(0);
            if ($row !== null) {
                break;
            }
        }
        expect($row)->not->toBeNull("{$title} for {$for} is not on the list");
        expect($row->getAttribute('data-wrapped'))->toBe($wrapped);
        $checkbox = $xpath->query('.//*[@data-test="wrap-checkbox"]', $row)->item(0);
        expect($checkbox)->not->toBeNull("{$title} has no checkbox on the list");

        expect(preg_match('/^(\w+)\((.*)\)$/', $checkbox->getAttribute('wire:click'), $call))->toBe(1);
        $params = json_decode('['.str_replace("'", '"', $call[2]).']', true);

        Livewire::actingAs($this->member->user);
        for ($i = 0; $i < $times; $i++) {
            $this->page->call($call[1], ...$params);
        }

        auth()->logout();
    }

    /**
     * What this list shows me now, without reloading it.
     */
    public function shows(): SeenWrappingChecklist
    {
        return SeenWrappingChecklist::read($this->page->html());
    }
}

/**
 * Each row of a list (shopping or wrapping) as [who it is for, title, whether it is marked private, its text].
 *
 * @return list<array{for: string, title: string, private: string, text: string}>
 */
function listPrivacy(DOMXPath $xpath, string $titleAttribute): array
{
    $rows = [];
    foreach ($xpath->query('//*[@'.$titleAttribute.']') as $row) {
        $rows[] = [
            'for' => $row->getAttribute('data-for'),
            'title' => $row->getAttribute($titleAttribute),
            'private' => $row->getAttribute('data-private'),
            'text' => trim((string) preg_replace('/\s+/', ' ', $row->textContent)),
        ];
    }

    return $rows;
}

/**
 * A person's page as I see it: their profile, their year-round ideas, and their history
 * (year => occasion name => gifts, each in the order shown), plus whether each idea and gift is marked private.
 */
final class SeenPersonPage
{
    /**
     * @param  list<array{string, string, string}>  $ideas  each [title, price, store]
     * @param  array<int, array<string, list<array{string, string, string, string}>>>  $history  each gift [title, price, status, who bought it]
     * @param  list<array{kind: string, title: string, private: string, text: string}>  $privacy  kind is "idea" or "gift"
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $birthday,
        public readonly ?int $age,
        public readonly string $interests,
        public readonly array $ideas,
        public readonly array $history,
        public readonly array $privacy,
        public readonly string $html,
    ) {}

    public static function read(string $html): self
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        // A profile detail is shown in the element marked with it; its value is the attribute or, if that is empty, the element's text.
        $detail = function (string $attribute) use ($xpath): ?string {
            $element = $xpath->query('//*[@'.$attribute.']')->item(0);
            if ($element === null) {
                return null;
            }

            $value = trim($element->getAttribute($attribute));

            return $value !== '' ? $value : trim($element->textContent);
        };

        $text = fn (DOMNode $node): string => trim((string) preg_replace('/\s+/', ' ', $node->textContent));

        $age = $detail('data-profile-age');

        $ideas = $privacy = [];
        foreach ($xpath->query('//*[@data-idea]') as $idea) {
            $ideas[] = [$idea->getAttribute('data-idea'), $idea->getAttribute('data-price'), $idea->getAttribute('data-store')];
            $privacy[] = ['kind' => 'idea', 'title' => $idea->getAttribute('data-idea'), 'private' => $idea->getAttribute('data-private'), 'text' => $text($idea)];
        }

        $history = [];
        foreach ($xpath->query('//*[@data-year]') as $year) {
            $history[(int) $year->getAttribute('data-year')] = [];

            foreach ($xpath->query('.//*[@data-history-occasion]', $year) as $occasion) {
                $gifts = [];
                foreach ($xpath->query('.//*[@data-history-gift]', $occasion) as $gift) {
                    $gifts[] = [
                        $gift->getAttribute('data-history-gift'),
                        $gift->getAttribute('data-price'),
                        $gift->getAttribute('data-status'),
                        $gift->getAttribute('data-buyer'),
                    ];
                    $privacy[] = ['kind' => 'gift', 'title' => $gift->getAttribute('data-history-gift'), 'private' => $gift->getAttribute('data-private'), 'text' => $text($gift)];
                }

                $history[(int) $year->getAttribute('data-year')][$occasion->getAttribute('data-history-occasion')] = $gifts;
            }
        }

        $heading = $xpath->query('//*[@data-flux-heading]')->item(0);

        return new self(
            $heading === null ? '' : $text($heading),
            $detail('data-profile-birthday'),
            $age === null || $age === '' ? null : (int) $age,
            $detail('data-profile-interests') ?? '',
            $ideas,
            $history,
            $privacy,
            $html,
        );
    }
}

/**
 * What the "New Christmas" window offered when I opened it, and what happened when I clicked Create straight away.
 */
final class NewChristmasDialog
{
    public function __construct(
        public readonly int $year,
        public readonly ?string $copyNote,
        public readonly ?string $refusal,
    ) {}
}

/**
 * The reminders on my dashboard as I see them after logging in, in the order shown.
 */
final class SeenDashboard
{
    /**
     * @param  array<string, int>  $birthdays  person name => days until their birthday (0 = today)
     * @param  list<string>  $birthdaysWithNoGift  people whose birthday reminder says "no gift yet"
     * @param  array<string, list<string>>  $noGift  occasion name => the people listed with no gift yet
     * @param  string|null  $christmas  the name of the Christmas on the Christmas card, or null when there is no card
     * @param  string  $christmasText  everything the Christmas card says, as one line of text
     * @param  array<string, list<string>>  $christmasLinks  link text on the Christmas card => where each such link goes (path)
     * @param  array<string, string>  $comingUp  occasion name => what its "Coming up" row says, as one line of text, in the order shown
     */
    public function __construct(
        public readonly array $birthdays,
        public readonly array $birthdaysWithNoGift,
        public readonly array $noGift,
        public readonly bool $allCaughtUp,
        public readonly string $html,
        public readonly ?string $christmas = null,
        public readonly string $christmasText = '',
        public readonly array $christmasLinks = [],
        public readonly array $comingUp = [],
    ) {}

    public static function read(string $html): self
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $birthdays = [];
        $flagged = [];
        foreach ($xpath->query('//*[@data-reminder-birthday]') as $row) {
            $name = $row->getAttribute('data-reminder-birthday');
            $birthdays[$name] = (int) $row->getAttribute('data-days');

            if ($row->hasAttribute('data-needs-gift')) {
                $flagged[] = $name;
            }
        }

        $noGift = [];
        foreach ($xpath->query('//*[@data-reminder-no-gift]') as $row) {
            $people = $row->getAttribute('data-people');
            $noGift[$row->getAttribute('data-reminder-no-gift')] = $people === '' ? [] : explode(', ', $people);
        }

        $text = fn (DOMNode $node): string => trim((string) preg_replace('/\s+/u', ' ', $node->textContent));

        $christmas = null;
        $christmasText = '';
        $christmasLinks = [];
        $card = $xpath->query('//*[@data-christmas]')->item(0);
        if ($card instanceof DOMElement) {
            $christmas = $card->getAttribute('data-christmas');
            $christmasText = $text($card);

            foreach ($xpath->query('.//a[@href]', $card) as $link) {
                $christmasLinks[$text($link)][] = (string) parse_url($link->getAttribute('href'), PHP_URL_PATH);
            }
        }

        $comingUp = [];
        foreach ($xpath->query('//*[@data-upcoming]') as $row) {
            $comingUp[$row->getAttribute('data-upcoming')] = $text($row);
        }

        return new self(
            $birthdays,
            $flagged,
            $noGift,
            $xpath->query('//*[@data-all-caught-up]')->length > 0,
            $html,
            $christmas,
            $christmasText,
            $christmasLinks,
            $comingUp,
        );
    }
}

/**
 * The settings on the server (or a fresh checkout) still name the app $name.
 */
function theAppIsStillCalled(string $name): void
{
    config(['app.name' => $name]);
}

/**
 * Read the logo and name at the top of the login page, as someone who is not logged in.
 */
function theLoginPage(): SeenBrand
{
    test()->assertGuest();

    return SeenBrand::read((string) test()->get(route('login'))->assertOk()->getContent());
}

/**
 * The brand at the top of a page: the first link on the page that holds a picture.
 * name is the link's text (what a screen reader says), drawing is each line of the picture as [shape, fill].
 */
final class SeenBrand
{
    /**
     * @param  list<array{string, string}>  $drawing
     */
    public function __construct(
        public readonly string $name,
        public readonly array $drawing,
        public readonly string $html,
    ) {}

    public static function read(string $html): self
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $link = $xpath->query('//a[.//*[local-name()="svg"]]')->item(0);
        expect($link)->not->toBeNull('The page has no logo link');

        $drawing = [];
        foreach ($xpath->query('.//*[local-name()="svg"][1]//*[local-name()="path"]', $link) as $path) {
            $drawing[] = [trim((string) preg_replace('/\s+/', ' ', $path->getAttribute('d'))), $path->getAttribute('fill')];
        }

        return new self(trim((string) preg_replace('/\s+/', ' ', $link->textContent)), $drawing, $html);
    }
}

/**
 * The icons a browser tab and a phone home screen pick up for Klaus.
 * tabIconIcoSizes is the pixel size of each picture inside the old-style tab icon.
 * homeScreenIconSize is [width, height] of the home screen icon.
 */
final class SeenAppIcons
{
    /**
     * @param  list<int>  $tabIconIcoSizes
     * @param  array{int, int}  $homeScreenIconSize
     */
    public function __construct(
        public readonly string $tabIconSvg,
        public readonly string $tabIconIcoFingerprint,
        public readonly array $tabIconIcoSizes,
        public readonly array $homeScreenIconSize,
    ) {}
}

/**
 * Read the browser tab icons and the phone home screen icon that Klaus ships.
 */
function theAppIcons(): SeenAppIcons
{
    $ico = (string) file_get_contents(public_path('favicon.ico'));
    $count = (int) unpack('v', substr($ico, 4, 2))[1];

    $sizes = [];
    for ($i = 0; $i < $count; $i++) {
        $width = ord($ico[6 + 16 * $i]);
        $sizes[] = $width === 0 ? 256 : $width;
    }

    $png = getimagesize(public_path('apple-touch-icon.png'));
    expect($png)->not->toBeFalse('The home screen icon is not a picture');

    return new SeenAppIcons(
        (string) file_get_contents(public_path('favicon.svg')),
        sha1($ico),
        $sizes,
        [(int) $png[0], (int) $png[1]],
    );
}

/**
 * Klaus's front page as someone who is not logged in sees it.
 * headlines are the page's main headings, text is everything on the page as read aloud (spaces collapsed),
 * links are [text, address] for every link, darkByDefault is true when the page is forced dark before
 * the device's light or dark setting is applied.
 */
final class SeenFrontPage
{
    /**
     * @param  list<string>  $headlines
     * @param  list<array{string, string}>  $links
     */
    public function __construct(
        public readonly string $tabTitle,
        public readonly array $headlines,
        public readonly string $text,
        public readonly array $links,
        public readonly bool $darkByDefault,
        public readonly string $html,
    ) {}

    public static function read(string $html): self
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);
        $clean = fn (string $text): string => trim((string) preg_replace('/\s+/', ' ', $text));

        $headlines = [];
        foreach ($xpath->query('//h1') as $h1) {
            $headlines[] = $clean($h1->textContent);
        }

        $links = [];
        foreach ($xpath->query('//a') as $a) {
            $links[] = [$clean($a->textContent), $a->getAttribute('href')];
        }

        $body = $xpath->query('//body')->item(0);
        $htmlClasses = preg_split('/\s+/', (string) $xpath->query('//html')->item(0)?->getAttribute('class')) ?: [];

        return new self(
            $clean((string) $xpath->query('//title')->item(0)?->textContent),
            $headlines,
            $clean((string) $body?->textContent),
            $links,
            in_array('dark', $htmlClasses, true),
            $html,
        );
    }

    /** The logo and name at the top of the page. */
    public function brand(): SeenBrand
    {
        return SeenBrand::read($this->html);
    }
}

/**
 * Someone who is not logged in opens Klaus's front page.
 */
function aVisitorOpensKlaus(): SeenFrontPage
{
    test()->assertGuest();

    return SeenFrontPage::read((string) test()->get(route('home'))->assertOk()->getContent());
}

/**
 * Read the logo and name at the top of the sign-up page, as someone who is not logged in.
 */
function theSignUpPage(): SeenBrand
{
    test()->assertGuest();

    return SeenBrand::read((string) test()->get(route('register'))->assertOk()->getContent());
}

/**
 * The colours Klaus's stylesheet gives each colour name, by day and at night.
 * A colour that the stylesheet does not set (and is not plain white or black) keeps its name, e.g. "neutral-800".
 */
final class KlausColours
{
    /**
     * @param  array<string, string>  $byDay
     * @param  array<string, string>  $atNight
     */
    private function __construct(
        private readonly array $byDay,
        private readonly array $atNight,
        public readonly string $stylesheet,
    ) {}

    public static function read(): self
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $grab = function (string $pattern) use ($css): array {
            preg_match($pattern, $css, $block);
            preg_match_all('/--color-([\w-]+)\s*:\s*([^;]+);/', $block[1] ?? '', $pairs, PREG_SET_ORDER);

            return array_column(array_map(fn (array $p): array => [$p[1], trim($p[2])], $pairs), 1, 0);
        };

        return new self($grab('/@theme\s*\{(.*?)\}/s'), $grab('/\.dark\s*\{(.*?)\}/s'), $css);
    }

    public function of(string $name, bool $atNight = false): string
    {
        $value = ($atNight ? ($this->atNight[$name] ?? null) : null) ?? $this->byDay[$name] ?? null;

        if ($value === null) {
            return match ($name) {
                'white' => '#ffffff',
                'black' => '#000000',
                default => $name,
            };
        }

        if (preg_match('/^var\(--color-([\w-]+)/', $value, $ref)) {
            return $this->of($ref[1], $atNight);
        }

        return strtolower($value);
    }

    /** The colour of the thin lines around boxes and between rows, when nothing else is said. */
    public function outlines(): string
    {
        preg_match('/border-color:\s*var\(--color-([\w-]+)/', $this->stylesheet, $match);

        return $this->of($match[1] ?? 'unset');
    }
}

/**
 * How a page looks.
 * backgroundByDay is the page's colour in light mode, backgroundsAtNight the colour(s) it fades through in dark mode.
 * greys is every grey on the page as [where it is used, its colour].
 * accent* is the colour of buttons and highlights, onAccent* the colour of writing on them. outlines is the default line colour.
 */
final class SeenLook
{
    /**
     * @param  list<string>  $backgroundsAtNight
     * @param  list<array{string, string}>  $greys
     */
    public function __construct(
        public readonly string $backgroundByDay,
        public readonly array $backgroundsAtNight,
        public readonly array $greys,
        public readonly string $accentByDay,
        public readonly string $accentAtNight,
        public readonly string $onAccentByDay,
        public readonly string $onAccentAtNight,
        public readonly string $outlines,
    ) {}

    public static function read(string $html): self
    {
        $colours = KlausColours::read();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $bodyClasses = preg_split('/\s+/', trim((string) $xpath->query('//body')->item(0)?->getAttribute('class'))) ?: [];
        $byDay = 'none';
        $atNight = [];
        foreach ($bodyClasses as $class) {
            if (preg_match('/^bg-(?!linear|radial|conic|gradient)([a-z]+(?:-\d+)?)(?:\/\d+)?$/', $class, $m)) {
                $byDay = $colours->of($m[1]);
            }
            if (preg_match('/^dark:(?:bg-(?!linear|radial|conic|gradient)|from-|via-|to-)([a-z]+(?:-\d+)?)(?:\/\d+)?$/', $class, $m)) {
                $atNight[] = $colours->of($m[1], true);
            }
        }

        $greys = [];
        foreach ($xpath->query('//*[@class]') as $element) {
            foreach (preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [] as $class) {
                if (preg_match('/(?:^|:)(?:bg|text|border(?:-[trblxy])?|from|via|to|ring(?:-offset)?|divide|fill|stroke|outline|shadow|placeholder|decoration)-((?:zinc|stone|neutral|gray|slate)-\d{2,3})(?:\/\d+)?$/', $class, $m)) {
                    $greys[$class] = [$class, $colours->of($m[1])];
                }
            }
        }

        return new self(
            $byDay,
            $atNight === [] ? [$byDay] : $atNight,
            array_values($greys),
            $colours->of('accent'),
            $colours->of('accent', true),
            $colours->of('accent-foreground'),
            $colours->of('accent-foreground', true),
            $colours->outlines(),
        );
    }
}

/**
 * How a page I have open looks.
 */
function theLookOf(SeenBrand|SeenFrontPage $page): SeenLook
{
    return SeenLook::read($page->html);
}

/**
 * The square tile the Klaus logo sits on, and the bow drawn on it, by day and at night.
 */
final class SeenLogoTile
{
    public function __construct(
        public readonly string $tileByDay,
        public readonly string $tileAtNight,
        public readonly string $bowByDay,
        public readonly string $bowAtNight,
    ) {}
}

/**
 * Read the logo tile at the top of a page I have open.
 */
function theLogoTileOn(SeenBrand|SeenFrontPage $page): SeenLogoTile
{
    $colours = KlausColours::read();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$page->html);
    $xpath = new DOMXPath($dom);

    $bow = $xpath->query('//a[.//*[local-name()="svg"]]//*[local-name()="svg"]')->item(0);
    expect($bow)->not->toBeNull('The page has no logo');

    /** @return array{string, string} */
    $colourOf = function (DOMElement $element, string $utility) use ($colours): array {
        $day = $night = null;
        foreach (preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [] as $class) {
            if (preg_match('/^'.$utility.'-((?!current\b)[a-z]+(?:-[a-z0-9]+)*)$/', $class, $m)) {
                $day = $m[1];
            }
            if (preg_match('/^dark:'.$utility.'-([a-z]+(?:-[a-z0-9]+)*)$/', $class, $m)) {
                $night = $m[1];
            }
        }

        return [$day === null ? 'none' : $colours->of($day), $colours->of($night ?? $day ?? 'none', true)];
    };

    $tile = $bow->parentNode;
    while ($tile instanceof DOMElement && $colourOf($tile, 'bg')[0] === 'none') {
        $tile = $tile->parentNode;
    }
    expect($tile)->toBeInstanceOf(DOMElement::class, 'The logo does not sit on a tile');

    // The bow is drawn in its own colour if it has one, otherwise in the colour of the writing around it.
    $drawnBy = $bow;
    while ($drawnBy instanceof DOMElement && $colourOf($drawnBy, 'text')[0] === 'none') {
        $drawnBy = $drawnBy->parentNode;
    }

    [$tileByDay, $tileAtNight] = $colourOf($tile, 'bg');
    [$bowByDay, $bowAtNight] = $drawnBy instanceof DOMElement ? $colourOf($drawnBy, 'text') : ['none', 'none'];

    return new SeenLogoTile($tileByDay, $tileAtNight, $bowByDay, $bowAtNight);
}

/**
 * The colours of the icons a browser tab and a phone home screen pick up for Klaus.
 * tabIconBowByDay / tabIconBowOnADarkTabBar are the bow's colour in the browser tab icon.
 * oldStyleTabIconBows is the bow's colour in each picture of the old-style tab icon (16, 32 and 48 pixels).
 * homeScreenTile is the colour at the corners of the home screen icon, homeScreenBow the colour drawn on it.
 */
final class SeenAppIconColours
{
    /**
     * @param  list<string>  $oldStyleTabIconBows
     */
    public function __construct(
        public readonly string $tabIconBowByDay,
        public readonly string $tabIconBowOnADarkTabBar,
        public readonly array $oldStyleTabIconBows,
        public readonly string $homeScreenTile,
        public readonly bool $homeScreenTileIsSolid,
        public readonly string $homeScreenBow,
    ) {}
}

/**
 * Read the colours of the browser tab icons and the phone home screen icon that Klaus ships.
 */
function theAppIconColours(): SeenAppIconColours
{
    $svg = (string) file_get_contents(public_path('favicon.svg'));
    preg_match('/<svg[^>]*\sstroke="([^"]+)"/', $svg, $byDay);
    preg_match('/prefers-color-scheme:\s*dark\)\s*\{[^}]*stroke:\s*([^;}\s]+)/', $svg, $atNight);

    $hex = fn (int $r, int $g, int $b): string => sprintf('#%02x%02x%02x', $r, $g, $b);

    // The bow in each old-style picture is its most used fully solid colour.
    $bows = [];
    $ico = new Imagick(public_path('favicon.ico'));
    foreach ($ico as $picture) {
        $counts = [];
        foreach ($picture->getPixelIterator() as $row) {
            foreach ($row as $pixel) {
                $c = $pixel->getColor(2);
                if ($c['a'] === 255) {
                    $key = $hex($c['r'], $c['g'], $c['b']);
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }
        }
        arsort($counts);
        $bows[] = (string) (array_key_first($counts) ?? 'none');
    }

    $png = imagecreatefrompng(public_path('apple-touch-icon.png'));
    expect($png)->not->toBeFalse('The home screen icon is not a picture');
    imagepalettetotruecolor($png);
    imagesavealpha($png, true);
    $width = imagesx($png);
    $height = imagesy($png);

    $colourAt = function (int $x, int $y) use ($png, $hex): array {
        $c = imagecolorsforindex($png, imagecolorat($png, $x, $y));

        return [$hex($c['red'], $c['green'], $c['blue']), $c['alpha'] === 0];
    };

    $corners = [$colourAt(0, 0), $colourAt($width - 1, 0), $colourAt(0, $height - 1), $colourAt($width - 1, $height - 1)];
    $tile = count(array_unique(array_column($corners, 0))) === 1 ? $corners[0][0] : 'mixed';

    // The bow is the most used colour inside the tile that is not the tile itself.
    $counts = [];
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            [$colour, $solid] = $colourAt($x, $y);
            if ($solid && $colour !== $tile) {
                $counts[$colour] = ($counts[$colour] ?? 0) + 1;
            }
        }
    }
    arsort($counts);

    return new SeenAppIconColours(
        strtolower($byDay[1] ?? 'none'),
        strtolower($atNight[1] ?? 'none'),
        $bows,
        $tile,
        ! in_array(false, array_column($corners, 1), true),
        (string) (array_key_first($counts) ?? 'none'),
    );
}

/**
 * A list page (People, Ideas, Occasions) as laid out for me: the boxes the lines sit in, and each line's choices.
 */
final class SeenListPage
{
    /** The attribute that names a line on each page. */
    public const array NAMED_BY = ['People' => 'data-person', 'Ideas' => 'data-idea', 'Occasions' => 'data-occasion'];

    /**
     * @param  list<array{heading: string, lines: list<string>}>  $boxes  each bordered box with a divider between its lines, in the order shown
     * @param  array<string, SeenListLine>  $lines
     * @param  list<string>  $unboxed  lines that do not sit directly in a box
     */
    public function __construct(
        public readonly array $boxes,
        public readonly array $lines,
        public readonly array $unboxed,
    ) {}

    /**
     * @param  list<string>  $linesAt  every kind of line whose choices are read
     * @param  string  $boxedAt  the lines that sit in the boxes; each one's keyed block is what sits in the box
     */
    public static function read(string $html, array $linesAt, string $boxedAt): self
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($dom);

        $isBox = 'contains(concat(" ", normalize-space(@class), " "), " border ")'
            .' and contains(concat(" ", normalize-space(@class), " "), " rounded-xl ")'
            .' and contains(concat(" ", normalize-space(@class), " "), " divide-y ")';

        $boxes = $unboxed = [];
        $boxIndex = new SplObjectStorage;
        foreach ($xpath->query('//*['.$isBox.']') as $box) {
            $heading = trim((string) $xpath->evaluate('string(preceding::*[@data-flux-heading][1])', $box));
            $boxIndex[$box] = count($boxes);
            $boxes[] = ['heading' => trim((string) preg_replace('/\s*\(\d+\)$/', '', $heading)), 'lines' => []];
        }

        foreach ($xpath->query($boxedAt) as $element) {
            $name = self::nameOf($element);
            $block = $xpath->query('ancestor-or-self::*[@*[name()="wire:key"]][1]', $element)->item(0);
            $parent = $block?->parentNode;

            if ($parent instanceof DOMElement && isset($boxIndex[$parent])) {
                $boxes[$boxIndex[$parent]]['lines'][] = $name;
            } else {
                $unboxed[] = $name;
            }
        }

        $lines = [];
        foreach ($linesAt as $query) {
            foreach ($xpath->query($query) as $element) {
                $lines[self::nameOf($element)] = SeenListLine::read($xpath, $element);
            }
        }

        return new self($boxes, $lines, $unboxed);
    }

    private static function nameOf(DOMElement $element): string
    {
        foreach (self::NAMED_BY as $attribute) {
            if ($element->hasAttribute($attribute)) {
                return $element->getAttribute($attribute);
            }
        }

        return '';
    }

    public function line(string $name): SeenListLine
    {
        expect(array_key_exists($name, $this->lines))->toBeTrue("The page has no line for {$name}");

        return $this->lines[$name];
    }
}

/**
 * One line on a list page: what I can do with it at a glance, what sits under its ⋯ More menu (choice => the question
 * I am asked first, or null), and whether all of its choices sit together at the end of the line.
 */
final class SeenListLine
{
    /**
     * @param  list<string>  $atHand
     * @param  array<string, ?string>  $underMore
     */
    public function __construct(
        public readonly array $atHand,
        public readonly array $underMore,
        public readonly bool $hasMoreMenu,
        public readonly bool $choicesSitTogetherAtTheEnd,
    ) {}

    public static function read(DOMXPath $xpath, DOMElement $line): self
    {
        $label = fn (DOMNode $node): string => trim((string) preg_replace('/\s+/', ' ', $node->textContent));
        $hasClass = fn (DOMElement $element, string $class): bool => in_array($class, preg_split('/\s+/', $element->getAttribute('class')) ?: [], true);

        $atHandElements = iterator_to_array($xpath->query('.//*[@*[name()="wire:click"]][not(ancestor::*[@data-flux-dropdown])]', $line));
        $menus = iterator_to_array($xpath->query('.//*[@data-flux-dropdown]', $line));

        $underMore = [];
        foreach ($xpath->query('.//*[@data-flux-menu]//*[@*[name()="wire:click"]]', $line) as $item) {
            $underMore[$label($item)] = $item->hasAttribute('wire:confirm') ? $item->getAttribute('wire:confirm') : null;
        }

        $children = iterator_to_array($xpath->query('./*', $line));
        $end = end($children);
        $together = $hasClass($line, 'justify-between')
            && count($children) >= 2
            && $end instanceof DOMElement
            && $hasClass($end, 'shrink-0');

        foreach ([...$atHandElements, ...$menus] as $choice) {
            $together = $together && $end instanceof DOMElement && $end->contains($choice);
        }

        return new self(
            array_map($label, $atHandElements),
            $underMore,
            $xpath->query('.//*[@data-flux-dropdown]//button[@aria-label="More actions"]', $line)->length === 1,
            $together,
        );
    }
}
