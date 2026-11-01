<?php

declare(strict_types=1);

it('shows me my private gifts with a Private badge and their own spent line, while our shared totals stay the same for both of us', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Sam')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Sam', 200);
    $me->setsBudget('Christmas 2026', 'Pluto', 100);

    $partner->addsGifts('Christmas 2026', 'Pluto',
        gift('Dog bed')->costing('80')->thatIs('Bought', by: 'Sam'),
        gift('Chew rope')->costing('12'),
    );
    $me->addsGifts('Christmas 2026', 'Sam',
        gift('Diving watch')->costing('149.99')->from('Seiko')->thatIs('Bought', by: 'Jason')->privately(),
        gift('Perfume')->costing('64')->from('Sephora')->privately(),
    );

    // I see my surprises for Sam, badged, and what I have spent on them on its own line.
    expect($me->occasion('Christmas 2026'))
        ->toShowGifts([
            'Pluto' => [['Dog bed', '$80', '', ''], ['Chew rope', '$12', '', '']],
            'Sam' => [['Diving watch', '$149.99', 'Seiko', ''], ['Perfume', '$64', 'Sephora', '']],
        ])
        ->toShowPrivateGifts(['Sam' => ['Diving watch', 'Perfume']])
        ->toShowMyPrivateGiftsSpent('$149.99')
        // Sam has a gift from me, so is not waiting for one.
        ->toShowNoGiftYet([]);

    // The shared Budget, Spent and Remaining leave my private gifts out, so we both see the same numbers.
    foreach ([$me, $partner] as $either) {
        expect($either->occasion('Christmas 2026'))
            ->toShowSpending([
                'Pluto' => ['$80', '$20'],
                'Sam' => ['$0', '$200'],
            ])
            ->toShowSeasonTotals(budget: '$300', spent: '$80', remaining: '$220');
    }
});
