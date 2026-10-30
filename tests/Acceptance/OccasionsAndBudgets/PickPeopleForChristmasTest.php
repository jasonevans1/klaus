<?php

declare(strict_types=1);

it('puts only the people I pick on Christmas and keeps them there when they are archived later', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Uncle Bob')->in('Family'));
    $me->adds(person('Dave')->in('Coworkers'));
    $me->adds(person('Old Boss')->in('Coworkers'));
    $me->archives('Old Boss');
    $me->startsChristmas(2026);

    expect($me->peopleICanAddTo('Christmas 2026'))->toBe(['Dave', 'Mum', 'Uncle Bob']);

    $me->addsTo('Christmas 2026', 'Mum', 'Uncle Bob');
    $me->setsBudget('Christmas 2026', 'Uncle Bob', 30);
    $me->archives('Uncle Bob');

    expect($me->peopleICanAddTo('Christmas 2026'))->toBe(['Dave']);
    expect($me->occasion('Christmas 2026'))
        ->toShowPeopleWithBudgets(['Family' => ['Mum' => 'No budget', 'Uncle Bob' => '$30']])
        ->toShowTotalBudget('$30');

    $me->removesFrom('Christmas 2026', 'Uncle Bob');

    expect($me->occasion('Christmas 2026'))
        ->toShowPeopleWithBudgets(['Family' => ['Mum' => 'No budget']])
        ->toShowTotalBudget('$0');
});
