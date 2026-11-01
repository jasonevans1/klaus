<?php

declare(strict_types=1);

it('shows my partner nothing about the gifts I am secretly buying for them, anywhere', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    // My partner is on our Christmas list too.
    $me->adds(person('Sam')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Sam', 200);
    $me->setsBudget('Christmas 2026', 'Pluto', 100);

    // Pluto's gifts we both know about.
    $partner->addsGifts('Christmas 2026', 'Pluto',
        gift('Dog bed')->costing('80')->thatIs('Bought', by: 'Sam'),
        gift('Chew rope')->costing('12'),
    );

    // My surprises for Sam: one already bought, one still an idea.
    $me->addsGifts('Christmas 2026', 'Sam',
        gift('Diving watch')->costing('149.99')->from('Seiko')->thatIs('Bought', by: 'Jason')->privately(),
        gift('Perfume')->costing('64')->from('Sephora')->privately(),
    );

    $surprise = ['Diving watch', 'Perfume', 'Seiko', 'Sephora', '149.99', 'My private gifts'];

    expect($partner->occasion('Christmas 2026'))
        ->toShowGifts([
            'Pluto' => [['Dog bed', '$80', '', ''], ['Chew rope', '$12', '', '']],
            'Sam' => [],
        ])
        ->toShowSpending([
            'Pluto' => ['$80', '$20'],
            'Sam' => ['$0', '$200'],
        ])
        ->toShowSeasonTotals(budget: '$300', spent: '$80', remaining: '$220')
        ->toShowNoGiftYet(['Sam'])
        ->toShowMyPrivateGiftsSpent(null)
        ->toShowNoTraceOf(...$surprise);

    expect($partner->shoppingList('Christmas 2026'))
        ->toListIdeas([['Pluto', 'Chew rope', '$12', '', '']])
        ->toShowIdeasLeft(1, '$12')
        ->toShowNoTraceOf(...$surprise);

    expect($partner->wrappingChecklist('Christmas 2026'))
        ->toListGiftsToWrap([['Pluto', 'Dog bed', 'to wrap']])
        ->toShowWrapped(0, of: 1)
        ->toShowNoTraceOf(...$surprise);

    // The warnings do not give the surprise away by counting my gifts.
    expect($partner->warningWhenRemoving('Sam', from: 'Christmas 2026'))->toBe('Remove Sam from this occasion?');
    expect($partner->warningWhenDeleting('Christmas 2026'))->toBe('Delete Christmas 2026 and its 2 gifts?');
});
