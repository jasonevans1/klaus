<?php

declare(strict_types=1);

it('gives Pluto the same budget as last birthday when next year\'s birthday shows up', function () {
    itIs('2025-11-01');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Pluto')->in('Family')->born(11, 20));

    theDailyJobRuns();
    $me->setsBudget("Pluto's birthday 2025", 'Pluto', 40);

    itIs('2026-11-01');
    theDailyJobRuns();

    expect($me->occasions())->toBe(["Pluto's birthday 2026", "Pluto's birthday 2025"]);
    expect($me->occasion("Pluto's birthday 2026"))
        ->toShowPeopleWithBudgets(['Family' => ['Pluto' => '$40']])
        ->toShowTotalBudget('$40');
});
