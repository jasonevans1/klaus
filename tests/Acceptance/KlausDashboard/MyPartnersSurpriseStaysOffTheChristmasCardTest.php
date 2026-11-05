<?php

declare(strict_types=1);

it('leaves my partner\'s secret gift out of my Christmas card, while they see it in their counts and on its own spent line', function () {
    itIs('2026-11-04');
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Mum', 200);
    $me->setsBudget('Christmas 2026', 'Pluto', 100);

    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->costing('80')->thatIs('Bought', by: 'Jason'));
    $partner->addsGifts('Christmas 2026', 'Mum',
        gift('Diving watch')->costing('149.99')->from('Seiko')->thatIs('Bought', by: 'Sam')->privately(),
    );

    expect($me->dashboard())
        ->toShowChristmasCard('Christmas 2026', when: 'in 51 days (Dec 25)', money: '$80 of $300 spent')
        ->toShowChristmasOverBudgetBy(null)
        ->toCountChristmasGifts(['Ideas' => 0, 'Bought' => 1, 'Wrapped' => 0, 'Given' => 0])
        ->toShowMyPrivateChristmasGiftsSpent(null)
        ->toShowNoTraceOf('Diving watch', 'Seiko', '149.99');

    // The shared spent stays the same for both of us; my partner sees their own surprise counted and on its own line.
    expect($partner->dashboard())
        ->toShowChristmasCard('Christmas 2026', when: 'in 51 days (Dec 25)', money: '$80 of $300 spent')
        ->toCountChristmasGifts(['Ideas' => 0, 'Bought' => 2, 'Wrapped' => 0, 'Given' => 0])
        ->toShowMyPrivateChristmasGiftsSpent('$149.99');
});
