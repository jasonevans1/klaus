<?php

declare(strict_types=1);
use App\Models\Occasion;

expect()->extend('toBeNamed', function (string $name) {
    expect($this->value)->toBeInstanceOf(SeenHousehold::class)
        ->and($this->value->name)->toBe($name);

    return $this;
});

expect()->extend('toHaveMembers', function (array $names) {
    expect($this->value)->toBeInstanceOf(SeenHousehold::class);

    $seen = $this->value->members;
    sort($seen);
    sort($names);

    expect($seen)->toBe($names);

    return $this;
});

expect()->extend('toBeTheSameHouseholdAs', function (SeenHousehold $other) {
    expect($this->value)->toBeInstanceOf(SeenHousehold::class)
        ->and($this->value->name)->toBe($other->name);

    $mine = $this->value->members;
    $theirs = $other->members;
    sort($mine);
    sort($theirs);

    expect($mine)->toBe($theirs);

    return $this;
});

expect()->extend('toBeRefusedWith', function (string $message) {
    expect($this->value instanceof InviteAttempt || $this->value instanceof ChristmasAttempt)->toBeTrue()
        ->and($this->value->refusal)->toBe($message);

    return $this;
});

expect()->extend('toHaveBeenSent', function () {
    expect($this->value)->toBeInstanceOf(InviteAttempt::class)
        ->and($this->value->refusal)->toBeNull();

    return $this;
});

expect()->extend('toShowGroups', function (array $groups) {
    expect($this->value)->toBeInstanceOf(SeenPeople::class)
        ->and($this->value->names())->toBe($groups);

    return $this;
});

expect()->extend('toShowSharedEntries', function (array $names) {
    expect($this->value)->toBeInstanceOf(SeenPeople::class);

    $shared = array_values(array_map(
        fn (SeenPerson $p) => $p->name,
        array_filter($this->value->everyone(), fn (SeenPerson $p) => $p->shared),
    ));
    sort($shared);
    sort($names);

    expect($shared)->toBe($names);

    return $this;
});

expect()->extend('toShowArchived', function (array $names) {
    expect($this->value)->toBeInstanceOf(SeenPeople::class);

    $archived = array_values(array_map(
        fn (SeenPerson $p) => $p->name,
        array_filter($this->value->everyone(), fn (SeenPerson $p) => $p->archived),
    ));
    sort($archived);
    sort($names);

    expect($archived)->toBe($names);

    return $this;
});

expect()->extend('toShowBirthday', function (string $name, ?string $birthday, ?int $age) {
    expect($this->value)->toBeInstanceOf(SeenPeople::class);

    $person = $this->value->person($name);

    expect($person->birthday)->toBe($birthday)
        ->and($person->age)->toBe($age);

    return $this;
});

expect()->extend('toShowPeopleWithBudgets', function (array $groups) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class)
        ->and($this->value->groups)->toBe($groups);

    return $this;
});

/**
 * Every person on the occasion page with exactly these gifts under them, each [title, price, store, link], in the order shown.
 */
expect()->extend('toShowGifts', function (array $giftsByPerson) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class)
        ->and($this->value->gifts)->toBe($giftsByPerson);

    return $this;
});

expect()->extend('toShowTotalBudget', function (string $total) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class)
        ->and($this->value->totalBudget)->toBe($total);

    return $this;
});

/**
 * The person's gift shows this status and buyer ("Bought by ..."), and offers the next step's button (none once Given).
 */
expect()->extend('toShowGiftStatus', function (string $person, string $gift, string $status, ?string $boughtBy = null) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class);

    $rows = array_values(array_filter(
        $this->value->giftProgress[$person] ?? [],
        fn (array $row) => $row['title'] === $gift,
    ));

    expect($rows)->toHaveCount(1, "Expected exactly one gift called {$gift} under {$person}");

    $button = match ($status) {
        'Idea' => 'Mark bought',
        'Bought' => 'Mark wrapped',
        'Wrapped' => 'Mark given',
        'Given' => null,
    };

    expect($rows[0]['status'])->toBe($status)
        ->and($rows[0]['buyer'])->toBe($boughtBy ?? '')
        ->and($rows[0]['button'])->toBe($button);

    if ($boughtBy === null) {
        expect($rows[0]['text'])->not->toContain('Bought by');
    } else {
        expect($rows[0]['text'])->toContain("Bought by {$boughtBy}");
    }

    return $this;
});

/**
 * Every person on the occasion page with exactly this spent and remaining, e.g. ['Pluto' => ['$80', '$20'], 'Mum' => ['$62.50', '-$12.50']].
 * Remaining is null for a person with no budget. Their row reads "Spent $80" and "$20 left" / "$12.50 over", or neither when there is no budget.
 */
expect()->extend('toShowSpending', function (array $byPerson) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class);

    $seen = array_map(
        fn (array $row) => [$row['spent'], $row['remaining'] === '' ? null : $row['remaining']],
        $this->value->spending,
    );

    expect($seen)->toBe($byPerson);

    foreach ($byPerson as $person => [$spent, $remaining]) {
        $text = $this->value->spending[$person]['text'];

        expect($text)->toContain("Spent {$spent}");

        if ($remaining === null) {
            expect($text)->not->toContain(' left')->not->toContain(' over');
        } elseif (str_starts_with($remaining, '-')) {
            expect($text)->toContain(substr($remaining, 1).' over')->not->toContain(' left');
        } else {
            expect($text)->toContain("{$remaining} left")->not->toContain(' over');
        }
    }

    return $this;
});

/**
 * The occasion's header shows these season totals, e.g. budget "$180", spent "$167.50", remaining "$12.50" (or "-$12.50").
 */
expect()->extend('toShowSeasonTotals', function (string $budget, string $spent, string $remaining) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class)
        ->and($this->value->totalBudget)->toBe($budget)
        ->and($this->value->totalSpent)->toBe($spent)
        ->and($this->value->totalRemaining)->toBe($remaining);

    return $this;
});

/**
 * Exactly these people are listed under "No gift yet", in the order shown; with nobody, there is no "No gift yet" section at all.
 */
expect()->extend('toShowNoGiftYet', function (array $names) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class)
        ->and($this->value->noGift)->toBe($names)
        ->and($this->value->showsNoGiftHeading)->toBe($names !== []);

    return $this;
});

/**
 * The shopping list shows exactly these gifts still to buy, each [who it is for, title, price, store, link], in the order shown.
 */
expect()->extend('toListIdeas', function (array $ideas) {
    expect($this->value)->toBeInstanceOf(SeenShoppingList::class)
        ->and($this->value->ideas)->toBe($ideas);

    return $this;
});

/**
 * The shopping list's header reads e.g. "3 ideas · $95"; with nothing left it reads "0 ideas · $0" and says "Nothing left to buy."
 */
expect()->extend('toShowIdeasLeft', function (int $count, string $total) {
    expect($this->value)->toBeInstanceOf(SeenShoppingList::class)
        ->and($this->value->count)->toBe((string) $count)
        ->and($this->value->total)->toBe($total)
        ->and($this->value->header)->toContain($count.' '.($count === 1 ? 'idea' : 'ideas').' · '.$total)
        ->and($this->value->saysNothingLeft)->toBe($count === 0);

    return $this;
});

/**
 * The wrapping checklist shows exactly these gifts, each [who it is for, title, "wrapped" or "to wrap"], in the order shown.
 */
expect()->extend('toListGiftsToWrap', function (array $gifts) {
    expect($this->value)->toBeInstanceOf(SeenWrappingChecklist::class)
        ->and($this->value->gifts)->toBe($gifts);

    return $this;
});

/**
 * The wrapping checklist's header reads e.g. "1 of 4 wrapped"; with nothing bought it reads "0 of 0 wrapped" and says "Nothing to wrap yet."
 */
expect()->extend('toShowWrapped', function (int $wrapped, int $of) {
    expect($this->value)->toBeInstanceOf(SeenWrappingChecklist::class)
        ->and($this->value->wrappedCount)->toBe((string) $wrapped)
        ->and($this->value->total)->toBe((string) $of)
        ->and($this->value->header)->toContain("{$wrapped} of {$of} wrapped")
        ->and($this->value->saysNothingToWrap)->toBe($of === 0);

    return $this;
});

/**
 * Exactly these gifts are marked private on the occasion page, with a "Private" badge on their row, e.g. ['Sam' => ['Diving watch']];
 * every other gift is marked as not private and has no badge.
 */
expect()->extend('toShowPrivateGifts', function (array $privateByPerson) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class);

    $seen = [];
    foreach ($this->value->giftPrivacy as $person => $gifts) {
        foreach ($gifts as $i => $gift) {
            expect($gift['private'])->toBeIn(['yes', 'no'], "{$gift['title']} for {$person} is not marked private or not private");

            $isPrivate = $gift['private'] === 'yes';
            $text = $this->value->giftProgress[$person][$i]['text'];

            expect((bool) preg_match('/\bPrivate\b/', $text))->toBe($isPrivate, "{$gift['title']} for {$person}: Private badge should be ".($isPrivate ? 'shown' : 'absent'));

            if ($isPrivate) {
                $seen[$person][] = $gift['title'];
            }
        }
    }

    expect($seen)->toBe($privateByPerson);

    return $this;
});

/**
 * The occasion's header reads "My private gifts: $X spent" with exactly this amount; null means there is no such line at all.
 */
expect()->extend('toShowMyPrivateGiftsSpent', function (?string $amount) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class)
        ->and($this->value->myPrivateSpent)->toBe($amount);

    if ($amount === null) {
        expect(html_entity_decode(strip_tags($this->value->html), ENT_QUOTES))->not->toContain('My private gifts');
    } else {
        expect($this->value->myPrivateText)->toBe("My private gifts: {$amount} spent");
    }

    return $this;
});

/**
 * Exactly these rows of the shopping list or wrapping checklist are marked private with a "Private" badge, each [who it is for, title];
 * every other row is marked as not private and has no badge.
 */
expect()->extend('toBadgeAsPrivate', function (array $rows) {
    expect($this->value instanceof SeenShoppingList || $this->value instanceof SeenWrappingChecklist)->toBeTrue();

    $seen = [];
    foreach ($this->value->privacy as $row) {
        expect($row['private'])->toBeIn(['yes', 'no'], "{$row['title']} for {$row['for']} is not marked private or not private");

        $isPrivate = $row['private'] === 'yes';

        expect((bool) preg_match('/\bPrivate\b/', $row['text']))->toBe($isPrivate, "{$row['title']} for {$row['for']}: Private badge should be ".($isPrivate ? 'shown' : 'absent'));

        if ($isPrivate) {
            $seen[] = [$row['for'], $row['title']];
        }
    }

    expect($seen)->toBe($rows);

    return $this;
});

/**
 * Nothing the page sends to my browser (text, attributes, hidden windows or page state) mentions any of these words, ignoring case.
 */
expect()->extend('toShowNoTraceOf', function (string ...$words) {
    expect($this->value instanceof SeenOccasion || $this->value instanceof SeenShoppingList || $this->value instanceof SeenWrappingChecklist || $this->value instanceof SeenIdeaList || $this->value instanceof SeenPersonPage || $this->value instanceof SeenDashboard)->toBeTrue()
        ->and($this->value->html)->not->toBe('');

    $page = mb_strtolower(html_entity_decode(stripslashes($this->value->html), ENT_QUOTES | ENT_HTML5));

    foreach ($words as $word) {
        expect(str_contains($page, mb_strtolower($word)))->toBeFalse("The page shows a trace of \"{$word}\"");
    }

    return $this;
});

/**
 * The Ideas page lists exactly these people with exactly these year-round ideas under them, each [title, price, store, link],
 * people and ideas in the order shown, e.g. ['Mum' => [], 'Pluto' => [['Dog bed', '$80', 'Petco', '']]].
 */
expect()->extend('toShowIdeas', function (array $ideasByPerson) {
    expect($this->value)->toBeInstanceOf(SeenIdeaList::class)
        ->and($this->value->ideas)->toBe($ideasByPerson);

    return $this;
});

/**
 * Exactly these ideas on the Ideas page are marked private, with a "Private" badge on their row, e.g. ['Sam' => ['Diving watch']];
 * every other idea is marked as not private and has no badge.
 */
expect()->extend('toShowPrivateIdeas', function (array $privateByPerson) {
    expect($this->value)->toBeInstanceOf(SeenIdeaList::class);

    $seen = [];
    foreach ($this->value->privacy as $person => $ideas) {
        foreach ($ideas as $idea) {
            expect($idea['private'])->toBeIn(['yes', 'no'], "{$idea['title']} for {$person} is not marked private or not private");

            $isPrivate = $idea['private'] === 'yes';

            expect((bool) preg_match('/\bPrivate\b/', $idea['text']))->toBe($isPrivate, "{$idea['title']} for {$person}: Private badge should be ".($isPrivate ? 'shown' : 'absent'));

            if ($isPrivate) {
                $seen[$person][] = $idea['title'];
            }
        }
    }

    expect($seen)->toBe($privateByPerson);

    return $this;
});

/**
 * Every person on the occasion page with exactly these year-round ideas listed under them, each [title, price], in the order shown,
 * and each with an "Add to this occasion" button, e.g. ['Mum' => [], 'Pluto' => [['Dog bed', '$80']]].
 */
expect()->extend('toShowYearRoundIdeas', function (array $ideasByPerson) {
    expect($this->value)->toBeInstanceOf(SeenOccasion::class);

    $seen = [];
    foreach ($this->value->yearRoundIdeas as $person => $ideas) {
        $seen[$person] = [];
        foreach ($ideas as $idea) {
            expect($idea['button'])->toBe('Add to this occasion', "{$idea['title']} for {$person} has no \"Add to this occasion\" button");

            $seen[$person][] = [$idea['title'], $idea['price']];
        }
    }

    expect($seen)->toBe($ideasByPerson);

    return $this;
});

/**
 * The person's page is headed with their name and shows exactly this birthday (as on the People page, e.g. "May 14, 2019"),
 * age and interests (line breaks kept).
 */
expect()->extend('toShowProfile', function (string $name, ?string $birthday, ?int $age, string $interests) {
    expect($this->value)->toBeInstanceOf(SeenPersonPage::class)
        ->and($this->value->name)->toBe($name)
        ->and($this->value->birthday)->toBe($birthday)
        ->and($this->value->age)->toBe($age)
        ->and($this->value->interests)->toBe($interests);

    return $this;
});

/**
 * The person's page lists exactly these year-round ideas, each [title, price, store], in the order shown.
 */
expect()->extend('toShowTheirIdeas', function (array $ideas) {
    expect($this->value)->toBeInstanceOf(SeenPersonPage::class)
        ->and($this->value->ideas)->toBe($ideas);

    return $this;
});

/**
 * The person's history is exactly this: year => occasion name => the gifts they got, each [title, price, status, who bought it],
 * years, occasions and gifts in the order shown, e.g. [2025 => ['Christmas 2025' => [['Dog bed', '$80', 'Given', 'Sam']]]].
 */
expect()->extend('toShowHistory', function (array $history) {
    expect($this->value)->toBeInstanceOf(SeenPersonPage::class)
        ->and($this->value->history)->toBe($history);

    return $this;
});

/**
 * Exactly these ideas and history gifts on the person's page are marked private, with a "Private" badge on their row;
 * every other one is marked as not private and has no badge.
 *
 * @param  list<string>  $ideas
 * @param  list<string>  $gifts
 */
expect()->extend('toBadgeTheirPrivate', function (array $ideas, array $gifts) {
    expect($this->value)->toBeInstanceOf(SeenPersonPage::class);

    $seen = ['idea' => [], 'gift' => []];
    foreach ($this->value->privacy as $row) {
        expect($row['private'])->toBeIn(['yes', 'no'], "{$row['title']} is not marked private or not private");

        $isPrivate = $row['private'] === 'yes';

        expect((bool) preg_match('/\bPrivate\b/', $row['text']))->toBe($isPrivate, "{$row['title']}: Private badge should be ".($isPrivate ? 'shown' : 'absent'));

        if ($isPrivate) {
            $seen[$row['kind']][] = $row['title'];
        }
    }

    expect($seen)->toBe(['idea' => $ideas, 'gift' => $gifts]);

    return $this;
});

/**
 * The "New Christmas" window had this year filled in and this note about what it copies (null: no note),
 * and clicking Create made the Christmas without complaint.
 */
expect()->extend('toHaveCreatedChristmas', function (int $year, ?string $copyNote) {
    expect($this->value)->toBeInstanceOf(NewChristmasDialog::class)
        ->and($this->value->year)->toBe($year, 'The New Christmas window filled in the wrong year')
        ->and($this->value->copyNote)->toBe($copyNote)
        ->and($this->value->refusal)->toBeNull();

    return $this;
});

/**
 * The dashboard reminds me of exactly these birthdays, person name => days away (0 = today), in the order shown.
 */
expect()->extend('toRemindOfBirthdays', function (array $daysByPerson) {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class)
        ->and($this->value->birthdays)->toBe($daysByPerson);

    if ($daysByPerson !== []) {
        expect($this->value->allCaughtUp)->toBeFalse('The dashboard says "All caught up." while there are reminders');
    }

    return $this;
});

/**
 * Exactly these birthday reminders say "no gift yet", in the order shown.
 */
expect()->extend('toSayNoGiftYetForTheBirthdayOf', function (array $names) {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class)
        ->and($this->value->birthdaysWithNoGift)->toBe($names);

    return $this;
});

/**
 * The dashboard lists exactly these occasions, in the order shown, each with exactly these people who have no gift yet
 * (in any order), e.g. ['Christmas 2026' => ['Grandpa', 'Mum']].
 */
expect()->extend('toRemindOfPeopleWithNoGift', function (array $peopleByOccasion) {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class);

    $sorted = function (array $byOccasion): array {
        return array_map(function (array $people): array {
            sort($people);

            return $people;
        }, $byOccasion);
    };

    expect(array_keys($this->value->noGift))->toBe(array_keys($peopleByOccasion))
        ->and($sorted($this->value->noGift))->toBe($sorted($peopleByOccasion));

    if ($peopleByOccasion !== []) {
        expect($this->value->allCaughtUp)->toBeFalse('The dashboard says "All caught up." while there are reminders');
    }

    return $this;
});

/**
 * The dashboard has no reminders at all and says "All caught up."
 */
expect()->extend('toSayAllCaughtUp', function () {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class)
        ->and($this->value->birthdays)->toBe([], 'The dashboard still reminds me of birthdays')
        ->and($this->value->noGift)->toBe([], 'The dashboard still lists people with no gift yet')
        ->and($this->value->allCaughtUp)->toBeTrue('The dashboard does not say "All caught up."');

    return $this;
});

/**
 * The two-loop Klaus bow, as drawn: two loops and two ribbon tails.
 */
const KLAUS_BOW = [
    'M12 11C9 5 3 5 3.5 9.5S9 12 12 11z',
    'M12 11c3-6 9-6 8.5-1.5S15 12 12 11z',
    'M12 11l-3.5 9',
    'M12 11l3.5 9',
];

/** A piece of the Laravel starter-kit logo. */
const LARAVEL_MARK = 'M17.2 5.633';

/** The fingerprint of the Laravel tab icon that came with the starter kit. */
const LARAVEL_TAB_ICON_FINGERPRINT = '4de5db574e6c589ef763ce57c4061f2269648ca4';

/**
 * The page's brand is named exactly "Klaus" and its picture is the bow as an outline: exactly the bow's four lines,
 * none of them filled in. No Laravel logo appears anywhere on the page.
 */
expect()->extend('toShowTheKlausLogo', function () {
    expect($this->value)->toBeInstanceOf(SeenBrand::class);

    expect($this->value->name)->toBe('Klaus', 'The brand is not named Klaus')
        ->and(array_map(fn (array $line): string => $line[0], $this->value->drawing))->toBe(KLAUS_BOW, 'The logo is not the Klaus bow')
        ->and(array_map(fn (array $line): string => $line[1], $this->value->drawing))->toBe(['none', 'none', 'none', 'none'], 'The bow is filled in instead of drawn as an outline')
        ->and(str_contains($this->value->html, LARAVEL_MARK))->toBeFalse('The page still shows the Laravel logo');

    return $this;
});

/**
 * The browser tab icon draws the Klaus bow (and turns white on a dark tab bar) with no Laravel logo,
 * the old-style tab icon is no longer the Laravel one and holds 16, 32 and 48 pixel pictures,
 * and the phone home screen icon is 180 by 180.
 */
expect()->extend('toShowTheKlausBow', function () {
    expect($this->value)->toBeInstanceOf(SeenAppIcons::class);

    foreach (KLAUS_BOW as $line) {
        expect(str_contains($this->value->tabIconSvg, $line))->toBeTrue("The tab icon is missing the bow line {$line}");
    }

    expect(str_contains($this->value->tabIconSvg, LARAVEL_MARK))->toBeFalse('The tab icon still shows the Laravel logo')
        ->and(str_contains($this->value->tabIconSvg, 'prefers-color-scheme: dark'))->toBeTrue('The tab icon does not adapt to a dark tab bar')
        ->and($this->value->tabIconIcoFingerprint)->not->toBe(LARAVEL_TAB_ICON_FINGERPRINT, 'The old-style tab icon is still the Laravel one')
        ->and($this->value->tabIconIcoSizes)->toBe([16, 32, 48], 'The old-style tab icon does not hold 16, 32 and 48 pixel pictures')
        ->and($this->value->homeScreenIconSize)->toBe([180, 180], 'The home screen icon is not 180 by 180');

    return $this;
});

/**
 * The front page's browser tab reads exactly "Klaus", its one main headline is the Klaus pitch, and it says
 * what Klaus is for: one list for the household, a shared budget, private gifts and birthday reminders.
 * It follows the device's light or dark setting instead of starting dark.
 */
expect()->extend('toTellWhatKlausIsFor', function () {
    expect($this->value)->toBeInstanceOf(SeenFrontPage::class);

    expect($this->value->tabTitle)->toBe('Klaus', 'The browser tab does not read exactly "Klaus"')
        ->and($this->value->headlines)->toBe(['Christmas and birthday gifts, planned together.'], 'The page does not have the Klaus headline as its one main heading');

    foreach ([
        'Klaus keeps one list for your household, so you never buy the same gift twice.',
        'One shared budget',
        "See what you've spent and what's left for each person.",
        'Private gifts',
        "Plan a surprise your partner can't see.",
        'Birthday reminders',
        'A heads-up before each birthday, so no one is missed.',
    ] as $line) {
        expect(str_contains($this->value->text, $line))->toBeTrue("The page does not say \"{$line}\"");
    }

    expect($this->value->darkByDefault)->toBeFalse('The page starts dark instead of following my light or dark setting');

    return $this;
});

/**
 * The front page has a "Log in" link to the login page and a "Get started" link to sign up and create a household,
 * and every link with those words goes there.
 */
expect()->extend('toOfferToLogInOrCreateAHousehold', function () {
    expect($this->value)->toBeInstanceOf(SeenFrontPage::class);

    foreach (['Log in' => route('login'), 'Get started' => route('register')] as $words => $goesTo) {
        $targets = array_values(array_map(
            fn (array $link): string => $link[1],
            array_filter($this->value->links, fn (array $link): bool => $link[0] === $words),
        ));

        expect($targets)->not->toBeEmpty("The page has no \"{$words}\" link")
            ->and(array_unique($targets))->toBe([$goesTo], "\"{$words}\" does not take me to {$goesTo}");
    }

    return $this;
});

/**
 * Nothing of the Laravel starter page is left: no "Laravel" anywhere on the page, no links to laravel.com
 * and no "Let's get started".
 */
expect()->extend('toLeaveNothingOfTheLaravelStarterPage', function () {
    expect($this->value)->toBeInstanceOf(SeenFrontPage::class);

    expect(str_contains($this->value->text, 'Laravel'))->toBeFalse('The page still says "Laravel"')
        ->and(str_contains($this->value->text, "Let's get started"))->toBeFalse('The page still says "Let\'s get started"')
        ->and(array_filter($this->value->links, fn (array $link): bool => str_contains($link[1], 'laravel.com')))->toBe([], 'The page still links to laravel.com')
        ->and(str_contains($this->value->html, LARAVEL_MARK))->toBeFalse('The page still shows the Laravel logo');

    return $this;
});

/**
 * Where I ended up is my dashboard.
 */
expect()->extend('toBeMyDashboard', function () {
    expect($this->value)->toBe(route('dashboard'), 'I did not land on my dashboard');

    return $this;
});

/**
 * The dashboard's Christmas card is for exactly this Christmas and says its name, when it is (e.g. "in 51 days (Dec 25)")
 * and what has been spent of the budget (e.g. "$135 of $130 spent").
 */
expect()->extend('toShowChristmasCard', function (string $name, string $when, string $money) {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class)
        ->and($this->value->christmas)->toBe($name, 'The dashboard shows no card for this Christmas')
        ->and($this->value->christmasText)->toContain($name)
        ->and($this->value->christmasText)->toContain($when)
        ->and($this->value->christmasText)->toContain($money);

    return $this;
});

/**
 * The Christmas card says "$X over" with exactly this amount; null means it says nothing about being over.
 */
expect()->extend('toShowChristmasOverBudgetBy', function (?string $amount) {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class)
        ->and($this->value->christmas)->not->toBeNull('The dashboard shows no Christmas card');

    preg_match_all('/(\$[\d,.]+) over\b/', $this->value->christmasText, $over);

    expect($over[1])->toBe($amount === null ? [] : [$amount]);

    return $this;
});

/**
 * The Christmas card shows exactly these counts for every gift stage, e.g. ['Ideas' => 2, 'Bought' => 2, 'Wrapped' => 1, 'Given' => 0].
 */
expect()->extend('toCountChristmasGifts', function (array $counts) {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class)
        ->and(array_keys($counts))->toBe(['Ideas', 'Bought', 'Wrapped', 'Given']);

    foreach ($counts as $stage => $count) {
        preg_match_all('/\b'.$stage.' (\d+)\b/', $this->value->christmasText, $found);

        expect($found[1])->toBe([(string) $count], "The Christmas card should say \"{$stage} {$count}\" once");
    }

    return $this;
});

/**
 * The Christmas card has a "Shopping list" link and a "Wrapping checklist" link, each going to that list for this occasion.
 */
expect()->extend('toOfferTheShoppingListAndWrappingChecklistOf', function (string $occasion) {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class);

    $id = Occasion::where('name', $occasion)->sole()->getKey();

    expect($this->value->christmasLinks['Shopping list'] ?? [])
        ->toBe([(string) parse_url(route('occasions.shopping', $id), PHP_URL_PATH)], 'The Christmas card has no Shopping list link to this occasion')
        ->and($this->value->christmasLinks['Wrapping checklist'] ?? [])
        ->toBe([(string) parse_url(route('occasions.wrapping', $id), PHP_URL_PATH)], 'The Christmas card has no Wrapping checklist link to this occasion');

    return $this;
});

/**
 * The Christmas card says "My private gifts: $X spent" with exactly this amount; null means it has no such line.
 */
expect()->extend('toShowMyPrivateChristmasGiftsSpent', function (?string $amount) {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class)
        ->and($this->value->christmas)->not->toBeNull('The dashboard shows no Christmas card');

    if ($amount === null) {
        expect($this->value->christmasText)->not->toContain('My private gifts');
    } else {
        expect($this->value->christmasText)->toContain("My private gifts: {$amount} spent");
    }

    return $this;
});

/**
 * "Coming up" lists exactly these occasions in the order shown, each row saying when it is and what has been spent,
 * e.g. ["Pluto's birthday 2026" => ['in 12 days (Dec 10)', '$25 of $50 spent']].
 */
expect()->extend('toListComingUp', function (array $occasions) {
    expect($this->value)->toBeInstanceOf(SeenDashboard::class)
        ->and(array_keys($this->value->comingUp))->toBe(array_keys($occasions));

    foreach ($occasions as $name => [$when, $money]) {
        expect($this->value->comingUp[$name])->toContain($name)->toContain($when)->toContain($money);
    }

    return $this;
});
