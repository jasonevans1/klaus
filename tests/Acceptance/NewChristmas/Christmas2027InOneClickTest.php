<?php

declare(strict_types=1);

it('sets up Christmas 2027 in one click with last year\'s people and budgets, no gifts, and nobody I archived', function () {
    itIs('2026-11-01');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Grandad')->in('Family'));
    $me->adds(person('Dave')->in('Coworkers'));
    $me->adds(person('Old Boss')->in('Coworkers'));

    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Mum', 100);
    $me->setsBudget('Christmas 2026', 'Grandad', 40);
    $me->setsBudget('Christmas 2026', 'Old Boss', 20);
    // Dave has no budget this year.
    $me->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->costing('35'));
    $me->addsGifts('Christmas 2026', 'Old Boss', gift('Mug')->costing('12'));
    $me->archives('Old Boss');

    expect($me->setsUpNextChristmas())
        ->toHaveCreatedChristmas(2027, 'Copies people and budgets from Christmas 2026.');

    expect($me->occasions())->toBe(['Christmas 2027', 'Christmas 2026']);
    expect($me->occasion('Christmas 2027'))
        ->toShowPeopleWithBudgets([
            'Family' => ['Grandad' => '$40', 'Mum' => '$100'],
            'Coworkers' => ['Dave' => 'No budget'],
        ])
        ->toShowTotalBudget('$140')
        ->toShowGifts(['Grandad' => [], 'Mum' => [], 'Dave' => []]);

    // Last Christmas is left as it was.
    expect($me->occasion('Christmas 2026'))
        ->toShowPeopleWithBudgets([
            'Family' => ['Grandad' => '$40', 'Mum' => '$100'],
            'Coworkers' => ['Dave' => 'No budget', 'Old Boss' => '$20'],
        ]);
});
