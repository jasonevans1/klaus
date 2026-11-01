<?php

declare(strict_types=1);

it('shows the ideas I jotted down for Pluto in March when we plan Christmas, and adding one to Christmas takes it off the idea list and makes it a Christmas gift', function () {
    itIs('2026-03-14');
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));

    // Months before Christmas, with no occasion yet.
    $me->addsIdeas('Pluto',
        gift('Dog bed')->costing('80')->from('Petco')->at('https://petco.example/dog-bed'),
        gift('Chew rope')->costing('12'),
    );

    expect($me->ideaList())->toShowIdeas([
        'Mum' => [],
        'Pluto' => [['Dog bed', '$80', 'Petco', 'https://petco.example/dog-bed'], ['Chew rope', '$12', '', '']],
    ]);

    itIs('2026-11-20');
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Pluto', 100);

    // Sam plans Christmas and sees my ideas for Pluto; until one is added they are only reminders, not Christmas gifts.
    expect($partner->occasion('Christmas 2026'))
        ->toShowYearRoundIdeas([
            'Mum' => [],
            'Pluto' => [['Dog bed', '$80'], ['Chew rope', '$12']],
        ])
        ->toShowGifts(['Mum' => [], 'Pluto' => []])
        ->toShowNoGiftYet(['Mum', 'Pluto'])
        ->toShowSpending(['Mum' => ['$0', null], 'Pluto' => ['$0', '$100']]);

    $partner->addsIdeaTo('Christmas 2026', for: 'Pluto', title: 'Dog bed');
    $partner->advancesGift('Christmas 2026', 'Pluto', 'Dog bed');

    // The dog bed is now one of Pluto's Christmas gifts, bought by Sam and counted in what we spent.
    expect($me->occasion('Christmas 2026'))
        ->toShowGifts([
            'Mum' => [],
            'Pluto' => [['Dog bed', '$80', 'Petco', 'https://petco.example/dog-bed']],
        ])
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Bought', boughtBy: 'Sam')
        ->toShowYearRoundIdeas(['Mum' => [], 'Pluto' => [['Chew rope', '$12']]])
        ->toShowNoGiftYet(['Mum'])
        ->toShowSpending(['Mum' => ['$0', null], 'Pluto' => ['$80', '$20']]);

    // It has left the idea list; the chew rope is still waiting there.
    expect($me->ideaList())->toShowIdeas([
        'Mum' => [],
        'Pluto' => [['Chew rope', '$12', '', '']],
    ]);
});
