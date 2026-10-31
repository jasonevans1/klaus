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
