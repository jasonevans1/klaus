<?php

declare(strict_types=1);

use App\Support\Budget;
use Illuminate\Support\Facades\Validator;

it('rejects budgets with cents, negative amounts or over 100000 dollars', function (mixed $value, bool $valid) {
    $passes = Validator::make(['b' => $value], ['b' => Budget::rules()])->passes();

    expect($passes)->toBe($valid);
})->with([
    'whole' => ['50', true],
    'blank' => [null, true],
    'max' => ['100000', true],
    'cents' => ['12.50', false],
    'negative' => ['-1', false],
    'too big' => ['100001', false],
]);
