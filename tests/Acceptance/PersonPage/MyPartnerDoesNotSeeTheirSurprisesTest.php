<?php

declare(strict_types=1);

it('shows me my secret gifts and ideas for Sam on Sam\'s page, marked private, and shows Sam none of them', function () {
    itIs('2025-11-20');
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Sam')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2025);
    $me->addsEveryoneTo('Christmas 2025');

    $me->addsGifts('Christmas 2025', 'Sam',
        gift('Diving watch')->costing('149.99')->from('Seiko')->thatIs('Given', by: 'Jason')->privately(),
        gift('Board game')->costing('45')->thatIs('Given', by: 'Jason'),
    );

    itIs('2026-03-14');
    $me->addsIdeas('Sam',
        gift('Perfume')->costing('64')->from('Sephora')->privately(),
        gift('Cookbook')->costing('30'),
    );

    itIs('2026-11-01');

    expect($me->personPage('Sam'))
        ->toShowTheirIdeas([['Perfume', '$64', 'Sephora'], ['Cookbook', '$30', '']])
        ->toShowHistory([2025 => ['Christmas 2025' => [
            ['Diving watch', '$149.99', 'Given', 'Jason'],
            ['Board game', '$45', 'Given', 'Jason'],
        ]]])
        ->toBadgeTheirPrivate(ideas: ['Perfume'], gifts: ['Diving watch']);

    expect($partner->personPage('Sam'))
        ->toShowTheirIdeas([['Cookbook', '$30', '']])
        ->toShowHistory([2025 => ['Christmas 2025' => [['Board game', '$45', 'Given', 'Jason']]]])
        ->toBadgeTheirPrivate(ideas: [], gifts: [])
        ->toShowNoTraceOf('Diving watch', 'Seiko', '149.99', 'Perfume', 'Sephora');
});
