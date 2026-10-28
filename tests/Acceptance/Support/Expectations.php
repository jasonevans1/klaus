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
    expect($this->value)->toBeInstanceOf(InviteAttempt::class)
        ->and($this->value->refusal)->toBe($message);

    return $this;
});

expect()->extend('toHaveBeenSent', function () {
    expect($this->value)->toBeInstanceOf(InviteAttempt::class)
        ->and($this->value->refusal)->toBeNull();

    return $this;
});
