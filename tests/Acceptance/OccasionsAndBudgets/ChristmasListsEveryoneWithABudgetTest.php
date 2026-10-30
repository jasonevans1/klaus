<?php

declare(strict_types=1);

it('lists everyone on Christmas 2026 with the budgets my partner and I set', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('grandma')->in('Family'));
    $me->adds(person('The Smiths')->in('Friends')->shared());
    $me->adds(person('Dave')->in('Coworkers'));
    $me->adds(person('Old Boss')->in('Coworkers'));
    $me->archives('Old Boss');

    $me->startsChristmas(2026);

    expect($me->occasion('Christmas 2026'))
        ->toShowPeopleWithBudgets([])
        ->toShowTotalBudget('$0');

    $me->addsEveryoneTo('Christmas 2026');

    $partner->setsBudget('Christmas 2026', 'Mum', 1250);
    $partner->setsBudget('Christmas 2026', 'grandma', 50);
    $partner->setsBudget('Christmas 2026', 'The Smiths', 50);
    // Sam changed their mind about Dave: no budget for him after all.
    $partner->setsBudget('Christmas 2026', 'Dave', 20);
    $partner->setsBudget('Christmas 2026', 'Dave', null);

    expect($me->occasion('Christmas 2026'))
        ->toShowPeopleWithBudgets([
            'Family' => ['grandma' => '$50', 'Mum' => '$1,250'],
            'Friends' => ['The Smiths' => '$50'],
            'Coworkers' => ['Dave' => 'No budget'],
        ])
        ->toShowTotalBudget('$1,350');
});
