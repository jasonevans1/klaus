<?php

declare(strict_types=1);

it('keeps the surprise ideas I note down for my partner off their idea list and their Christmas, even once I add them to Christmas', function () {
    itIs('2026-03-14');
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Sam')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));

    $me->addsIdeas('Sam',
        gift('Diving watch')->costing('149.99')->from('Seiko')->privately(),
        gift('Board game')->costing('45'),
    );

    $surprise = ['Diving watch', 'Seiko', '149.99'];

    expect($me->ideaList())
        ->toShowIdeas([
            'Pluto' => [],
            'Sam' => [['Diving watch', '$149.99', 'Seiko', ''], ['Board game', '$45', '', '']],
        ])
        ->toShowPrivateIdeas(['Sam' => ['Diving watch']]);

    expect($partner->ideaList())
        ->toShowIdeas(['Pluto' => [], 'Sam' => [['Board game', '$45', '', '']]])
        ->toShowPrivateIdeas([])
        ->toShowNoTraceOf(...$surprise);

    itIs('2026-11-20');
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');

    expect($me->occasion('Christmas 2026'))
        ->toShowYearRoundIdeas(['Pluto' => [], 'Sam' => [['Diving watch', '$149.99'], ['Board game', '$45']]]);

    expect($partner->occasion('Christmas 2026'))
        ->toShowYearRoundIdeas(['Pluto' => [], 'Sam' => [['Board game', '$45']]])
        ->toShowNoTraceOf(...$surprise);

    $me->addsIdeaTo('Christmas 2026', for: 'Sam', title: 'Diving watch');

    // On Christmas it is still my private gift.
    expect($me->occasion('Christmas 2026'))
        ->toShowGifts(['Pluto' => [], 'Sam' => [['Diving watch', '$149.99', 'Seiko', '']]])
        ->toShowPrivateGifts(['Sam' => ['Diving watch']])
        ->toShowYearRoundIdeas(['Pluto' => [], 'Sam' => [['Board game', '$45']]]);

    expect($partner->occasion('Christmas 2026'))
        ->toShowGifts(['Pluto' => [], 'Sam' => []])
        ->toShowNoGiftYet(['Pluto', 'Sam'])
        ->toShowYearRoundIdeas(['Pluto' => [], 'Sam' => [['Board game', '$45']]])
        ->toShowNoTraceOf(...$surprise);

    expect($partner->ideaList())
        ->toShowIdeas(['Pluto' => [], 'Sam' => [['Board game', '$45', '', '']]])
        ->toShowNoTraceOf(...$surprise);
});
