<?php

declare(strict_types=1);

it('shows on Pluto\'s page what he got last Christmas and every Christmas before, newest year first, without the ideas we never bought', function () {
    itIs('2024-11-20');
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family')->born(5, 14, 2019)->interests("Squeaky toys\nLong walks on the beach"));
    $me->adds(person('Mum')->in('Family'));

    $me->startsChristmas(2024);
    $me->addsEveryoneTo('Christmas 2024');
    $me->addsGifts('Christmas 2024', 'Pluto', gift('Tennis balls')->costing('15')->thatIs('Given', by: 'Jason'));

    itIs('2025-11-20');
    $me->startsChristmas(2025);
    $me->addsEveryoneTo('Christmas 2025');
    $partner->addsGifts('Christmas 2025', 'Pluto',
        gift('Dog bed')->costing('80')->from('Petco')->thatIs('Given', by: 'Sam'),
        gift('Chew rope')->costing('12')->thatIs('Wrapped', by: 'Jason'),
        gift('Squeaky duck')->costing('9')->thatIs('Bought', by: 'Sam'),
        gift('Raincoat')->costing('35'),
    );
    $partner->addsGifts('Christmas 2025', 'Mum', gift('Scarf')->costing('40')->thatIs('Given', by: 'Sam'));

    itIs('2026-03-14');
    $me->addsIdeas('Pluto', gift('Ball launcher')->costing('60')->from('Chewy'));

    itIs('2026-11-20');
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->addsGifts('Christmas 2026', 'Pluto',
        gift('Puzzle feeder')->costing('25')->thatIs('Bought', by: 'Jason'),
        gift('Antler chew')->costing('18'),
    );

    // Either of us can open Pluto's page; Sam looks.
    expect($partner->personPage('Pluto'))
        ->toShowProfile('Pluto', birthday: 'May 14, 2019', age: 7, interests: "Squeaky toys\nLong walks on the beach")
        ->toShowTheirIdeas([['Ball launcher', '$60', 'Chewy']])
        ->toShowHistory([
            2026 => ['Christmas 2026' => [['Puzzle feeder', '$25', 'Bought', 'Jason']]],
            2025 => ['Christmas 2025' => [
                ['Dog bed', '$80', 'Given', 'Sam'],
                ['Chew rope', '$12', 'Wrapped', 'Jason'],
                ['Squeaky duck', '$9', 'Bought', 'Sam'],
            ]],
            2024 => ['Christmas 2024' => [['Tennis balls', '$15', 'Given', 'Jason']]],
        ]);
});
