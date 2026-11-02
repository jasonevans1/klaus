<?php

declare(strict_types=1);

it('copies Christmas 2026 as I left it, not the older Christmas 2025', function () {
    itIs('2026-11-01');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Dave')->in('Coworkers'));

    $me->startsChristmas(2025);
    $me->addsEveryoneTo('Christmas 2025');
    $me->setsBudget('Christmas 2025', 'Mum', 60);
    $me->setsBudget('Christmas 2025', 'Dave', 25);

    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Mum', 80);
    $me->removesFrom('Christmas 2026', 'Dave');

    expect($me->setsUpNextChristmas())
        ->toHaveCreatedChristmas(2027, 'Copies people and budgets from Christmas 2026.');

    expect($me->occasion('Christmas 2027'))
        ->toShowPeopleWithBudgets(['Family' => ['Mum' => '$80']])
        ->toShowTotalBudget('$80');
});
