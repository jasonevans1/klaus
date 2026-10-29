<?php

declare(strict_types=1);

use App\Rules\ValidBirthday;
use Illuminate\Support\Facades\Validator;

function birthdayErrors(array $data): array
{
    return Validator::make($data, [
        'birthday_month' => ['nullable', 'integer', new ValidBirthday],
    ])->errors()->get('birthday_month');
}

it('accepts an empty birthday', function () {
    expect(birthdayErrors([]))->toBe([])
        ->and(birthdayErrors(['birthday_month' => '', 'birthday_day' => '', 'birthday_year' => '']))->toBe([]);
});

it('accepts a month and day without a year', function () {
    expect(birthdayErrors(['birthday_month' => '3', 'birthday_day' => '14', 'birthday_year' => '']))->toBe([]);
});

it('rejects a month without a day', function () {
    expect(birthdayErrors(['birthday_month' => '3', 'birthday_day' => '']))->toBe(['Enter both the month and day.']);
});

it('rejects a day without a month even though the month is nullable', function () {
    expect(birthdayErrors(['birthday_month' => null, 'birthday_day' => '14']))->toBe(['Enter both the month and day.'])
        ->and(Validator::make(
            ['form' => ['birthday_month' => '', 'birthday_day' => '14']],
            ['form.birthday_month' => ['nullable', 'integer', new ValidBirthday]],
        )->errors()->get('form.birthday_month'))->toBe(['Enter both the month and day.']);
});

it('rejects a year without a month and day', function () {
    expect(birthdayErrors(['birthday_year' => '1980']))->toBe(['Add the month and day too.']);
});

it('rejects a day that does not exist in the month', function () {
    expect(birthdayErrors(['birthday_month' => '4', 'birthday_day' => '31']))->toBe(["That date doesn't exist."])
        ->and(birthdayErrors(['birthday_month' => '13', 'birthday_day' => '1']))->toBe(["That date doesn't exist."])
        ->and(birthdayErrors(['birthday_month' => '1', 'birthday_day' => '0']))->toBe(["That date doesn't exist."]);
});

it('accepts february 29 without a year but rejects it in a non-leap year', function () {
    expect(birthdayErrors(['birthday_month' => '2', 'birthday_day' => '29']))->toBe([])
        ->and(birthdayErrors(['birthday_month' => '2', 'birthday_day' => '29', 'birthday_year' => '2000']))->toBe([])
        ->and(birthdayErrors(['birthday_month' => '2', 'birthday_day' => '29', 'birthday_year' => '2001']))->toBe(["That date doesn't exist."]);
});

it('rejects a year before 1900 or after this year', function () {
    $next = (string) (now()->year + 1);

    expect(birthdayErrors(['birthday_month' => '3', 'birthday_day' => '14', 'birthday_year' => '1899']))->toBe(['Enter a year between 1900 and this year.'])
        ->and(birthdayErrors(['birthday_month' => '3', 'birthday_day' => '14', 'birthday_year' => $next]))->toBe(['Enter a year between 1900 and this year.'])
        ->and(birthdayErrors(['birthday_month' => '3', 'birthday_day' => '14', 'birthday_year' => '1900']))->toBe([])
        ->and(birthdayErrors(['birthday_month' => '3', 'birthday_day' => '14', 'birthday_year' => (string) now()->year]))->toBe([]);
});
