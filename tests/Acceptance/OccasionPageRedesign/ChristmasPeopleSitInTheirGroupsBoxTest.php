<?php

declare(strict_types=1);

it('shows each group on Christmas in its own box, every person\'s line keeping its budget box and Remove tucked under More, still warning about their gifts', function () {
    itIs('2026-11-20');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Uncle Bob')->in('Friends'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Pluto', 100);
    $me->addsGifts('Christmas 2026', 'Pluto',
        gift('Dog bed')->costing('80')->thatIs('Bought', by: 'Jason'),
        gift('Chew rope')->costing('12')->thatIs('Bought', by: 'Jason'),
    );
    $me->addsGifts('Christmas 2026', 'Uncle Bob', gift('Socks')->costing('15')->thatIs('Bought', by: 'Jason'));

    expect($me->occasionPage('Christmas 2026'))
        ->toListInBoxes(['Family' => ['Mum', 'Pluto'], 'Friends' => ['Uncle Bob']])
        ->toLineUp('Mum', atHand: [], underMore: ['Remove' => 'Remove Mum from this occasion?'])
        ->toLineUp('Pluto', atHand: [], underMore: ['Remove' => 'Remove Pluto and their 2 gifts from this occasion?'])
        ->toLineUp('Uncle Bob', atHand: [], underMore: ['Remove' => 'Remove Uncle Bob and their 1 gift from this occasion?']);

    expect($me->occasion('Christmas 2026'))
        ->toKeepABudgetBoxOnTheLineOf('Mum', 'Pluto', 'Uncle Bob')
        ->toShowSpending(['Mum' => ['$0', null], 'Pluto' => ['$92', '$8'], 'Uncle Bob' => ['$15', null]]);

    // Removing Uncle Bob from the More menu takes him, and his socks, off Christmas.
    $me->picksFromMore('Remove', for: 'Uncle Bob', on: 'Christmas 2026');

    expect($me->occasion('Christmas 2026'))
        ->toShowPeopleWithBudgets(['Family' => ['Mum' => 'No budget', 'Pluto' => '$100']])
        ->toShowSeasonTotals('$100', '$92', '$8');
});
