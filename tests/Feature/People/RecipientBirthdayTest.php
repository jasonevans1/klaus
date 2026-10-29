<?php

declare(strict_types=1);

use App\Models\Recipient;

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
