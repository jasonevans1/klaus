<?php

declare(strict_types=1);

it('shows me this Christmas at a glance: what we have spent of the budget, how far over we are, and how many gifts are at each stage', function () {
    itIs('2026-11-04');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Pluto', 100);
    $me->setsBudget('Christmas 2026', 'Mum', 30);

    $me->addsGifts('Christmas 2026', 'Pluto',
        gift('Dog bed')->costing('80')->thatIs('Bought', by: 'Jason'),
        gift('Chew rope')->costing('15')->thatIs('Wrapped', by: 'Jason'),
    );
    $me->addsGifts('Christmas 2026', 'Mum',
        gift('Scarf')->costing('40')->thatIs('Bought', by: 'Jason'),
        gift('Book')->costing('20'),
        gift('Candle')->costing('10'),
    );

    // Ideas are not spent yet: $80 + $15 + $40 against a $130 budget.
    expect($me->dashboard())
        ->toShowChristmasCard('Christmas 2026', when: 'in 51 days (Dec 25)', money: '$135 of $130 spent')
        ->toShowChristmasOverBudgetBy('$5')
        ->toCountChristmasGifts(['Ideas' => 2, 'Bought' => 2, 'Wrapped' => 1, 'Given' => 0])
        ->toOfferTheShoppingListAndWrappingChecklistOf('Christmas 2026')
        ->toShowMyPrivateChristmasGiftsSpent(null);
});
