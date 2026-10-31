<?php

declare(strict_types=1);

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
