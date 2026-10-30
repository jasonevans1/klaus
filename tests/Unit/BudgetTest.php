<?php

declare(strict_types=1);

use App\Support\Budget;

it('converts whole dollars to cents', function () {
    expect(Budget::toCents('50'))->toBe(5000)
        ->and(Budget::toCents(50))->toBe(5000);
});

it('treats a blank budget as no budget', function () {
    expect(Budget::toCents(null))->toBeNull()
        ->and(Budget::toCents(''))->toBeNull();
});

it('formats cents as whole dollars with thousands separators', function () {
    expect(Budget::format(125000))->toBe('$1,250')
        ->and(Budget::format(0))->toBe('$0');
});

it('formats a missing budget as no budget', function () {
    expect(Budget::format(null))->toBe('No budget');
});
