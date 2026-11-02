<?php

declare(strict_types=1);

it('puts Pluto\'s birthday on my occasions a month ahead, with Pluto on it, without me creating it', function () {
    itIs('2026-10-31');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Pluto')->in('Family')->born(12, 1));
    $me->adds(person('Dave')->in('Coworkers'));
    $me->startsChristmas(2026);

    theDailyJobRuns();

    // Pluto's birthday is still more than 30 days away.
    expect($me->occasions())->toBe(['Christmas 2026']);

    itIs('2026-11-01');
    theDailyJobRuns();
    itIs('2026-11-02');
    theDailyJobRuns();

    expect($me->occasions())->toBe(['Christmas 2026', "Pluto's birthday 2026"]);
    expect($me->occasion("Pluto's birthday 2026"))
        ->toShowPeopleWithBudgets(['Family' => ['Pluto' => 'No budget']])
        ->toShowTotalBudget('$0');
});
