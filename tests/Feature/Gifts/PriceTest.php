<?php

declare(strict_types=1);

use App\Support\Price;
use Illuminate\Support\Facades\Validator;

it('turns typed dollars and cents into exact cents', function () {
    expect(Price::toCents('24.99'))->toBe(2499)
        ->and(Price::toCents('19.99'))->toBe(1999)
        ->and(Price::toCents('25'))->toBe(2500)
        ->and(Price::toCents('0.1'))->toBe(10)
        ->and(Price::toCents(25))->toBe(2500);
});

it('treats a blank price as no price', function () {
    expect(Price::toCents(''))->toBeNull()
        ->and(Price::toCents(null))->toBeNull();
});

it('shows prices with cents only when there are cents', function () {
    expect(Price::format(2499))->toBe('$24.99')
        ->and(Price::format(2500))->toBe('$25')
        ->and(Price::format(123456))->toBe('$1,234.56')
        ->and(Price::format(650))->toBe('$6.50')
        ->and(Price::format(99))->toBe('$0.99')
        ->and(Price::format(0))->toBe('$0');
});

it('shows a missing price as No price', function () {
    expect(Price::format(null))->toBe('No price');
});

it('turns cents back into the text shown in the price box', function () {
    expect(Price::toDollars(2499))->toBe('24.99')
        ->and(Price::toDollars(2500))->toBe('25')
        ->and(Price::toDollars(null))->toBe('');
});

it('rejects negative prices and prices with more than two decimals', function () {
    $passes = fn (string $v): bool => Validator::make(['p' => $v], ['p' => Price::rules()])->passes();

    expect($passes('-1'))->toBeFalse()
        ->and($passes('1.234'))->toBeFalse()
        ->and($passes('100001'))->toBeFalse()
        ->and($passes('6.5'))->toBeTrue()
        ->and($passes(''))->toBeTrue();
});
