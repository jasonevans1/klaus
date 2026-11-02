<?php

declare(strict_types=1);

use App\Models\Recipient;
use Carbon\CarbonImmutable;

function recipientBorn(?int $month, ?int $day, ?int $year = null): Recipient
{
    return new Recipient(['birthday_month' => $month, 'birthday_day' => $day, 'birthday_year' => $year]);
}

it('formats the birthday with and without a year and gives age only when the year is known', function () {
    $this->travelTo('2026-10-28');

    expect(recipientBorn(null, null)->birthdayLabel())->toBeNull()
        ->and(recipientBorn(null, null)->age())->toBeNull()
        ->and(recipientBorn(3, 14)->birthdayLabel())->toBe('Mar 14')
        ->and(recipientBorn(3, 14)->age())->toBeNull()
        ->and(recipientBorn(3, 14, 1980)->birthdayLabel())->toBe('Mar 14, 1980')
        ->and(recipientBorn(3, 14, 1980)->age())->toBe(46);
});

it('does not count the age up until the birthday has passed this year', function () {
    $this->travelTo('2026-03-13');
    expect(recipientBorn(3, 14, 1980)->age())->toBe(45);

    $this->travelTo('2026-03-14');
    expect(recipientBorn(3, 14, 1980)->age())->toBe(46);
});

it('gives this year\'s birthday when it is still to come', function () {
    $next = recipientBorn(11, 20)->nextBirthday(CarbonImmutable::create(2026, 11, 1));

    expect($next->toDateString())->toBe('2026-11-20');
});

it('gives today\'s date when the birthday is today', function () {
    $next = recipientBorn(11, 1)->nextBirthday(CarbonImmutable::create(2026, 11, 1, 15));

    expect($next->toDateString())->toBe('2026-11-01');
});

it('gives next year\'s birthday when this year\'s has passed', function () {
    $next = recipientBorn(3, 14)->nextBirthday(CarbonImmutable::create(2026, 11, 1));

    expect($next->toDateString())->toBe('2027-03-14');
});

it('puts a Feb 29 birthday on Feb 28 in a non-leap year', function () {
    expect(recipientBorn(2, 29)->nextBirthday(CarbonImmutable::create(2026, 11, 1))->toDateString())->toBe('2027-02-28')
        ->and(recipientBorn(2, 29)->nextBirthday(CarbonImmutable::create(2027, 11, 1))->toDateString())->toBe('2028-02-29');
});

it('has no next birthday without a birthday month and day', function () {
    $today = CarbonImmutable::create(2026, 11, 1);

    expect(recipientBorn(null, null)->nextBirthday($today))->toBeNull()
        ->and(recipientBorn(3, null)->nextBirthday($today))->toBeNull()
        ->and(recipientBorn(null, 14)->nextBirthday($today))->toBeNull();
});
