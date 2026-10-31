<?php

declare(strict_types=1);

use App\Enums\GiftStatus;

it('lists gift statuses in the order Idea, Bought, Wrapped, Given', function () {
    expect(GiftStatus::cases())->toBe([GiftStatus::Idea, GiftStatus::Bought, GiftStatus::Wrapped, GiftStatus::Given]);
});

it('labels each gift status for display', function () {
    expect(array_map(fn (GiftStatus $s) => $s->label(), GiftStatus::cases()))
        ->toBe(['Idea', 'Bought', 'Wrapped', 'Given']);
});

it('moves each status to the next one in the order', function () {
    expect(GiftStatus::Idea->next())->toBe(GiftStatus::Bought)
        ->and(GiftStatus::Bought->next())->toBe(GiftStatus::Wrapped)
        ->and(GiftStatus::Wrapped->next())->toBe(GiftStatus::Given);
});

it('has no status after Given', function () {
    expect(GiftStatus::Given->next())->toBeNull();
});
