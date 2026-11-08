<?php

declare(strict_types=1);

it('sets up Birthdays 2027 with everyone who has a birthday, last year\'s budgets and each birthday date, and only once', function () {
    itIs('2026-11-08');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Pluto')->in('Family')->born(12, 10));
    $me->adds(person('Mum')->in('Family')->born(3, 4, 1958));
    $me->adds(person('Grandpa')->in('Family'));
    $me->adds(person('Dave')->in('Coworkers')->born(7, 1));
    $me->adds(person('Old Boss')->in('Coworkers')->born(5, 5));

    expect($me->setsUpBirthdays(2026))->toHaveCreatedBirthdays(null);
    $me->setsBudget('Birthdays 2026', 'Pluto', 50);
    $me->setsBudget('Birthdays 2026', 'Mum', 40);
    $me->setsBudget('Birthdays 2026', 'Old Boss', 20);
    // Dave has no budget this year.

    $me->archives('Old Boss');
    $me->adds(person('Nan')->in('Family')->born(9, 14));

    expect($me->setsUpBirthdays(2027))->toHaveCreatedBirthdays('Copies budgets from Birthdays 2026.');

    expect($me->occasions())->toBe(['Birthdays 2027', 'Birthdays 2026']);
    // Grandpa has no birthday and Old Boss is archived, so neither is on it. Nan is new, so she has no budget yet.
    expect($me->occasion('Birthdays 2027'))
        ->toShowPeopleWithBudgets([
            'Family' => ['Mum' => '$40', 'Nan' => 'No budget', 'Pluto' => '$50'],
            'Coworkers' => ['Dave' => 'No budget'],
        ])
        ->toShowTotalBudget('$90')
        ->toShowBirthdays(['Mum' => 'Mar 4', 'Nan' => 'Sep 14', 'Pluto' => 'Dec 10', 'Dave' => 'Jul 1']);

    expect($me->setsUpBirthdays(2027))->toHaveRefusedBirthdays('You already have Birthdays 2027.');
    expect($me->occasions())->toBe(['Birthdays 2027', 'Birthdays 2026']);
});
