<?php

declare(strict_types=1);

it('shows both of us every gift still to buy for Christmas, for everyone, on one list', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Grandad')->in('Family'));

    // Last Christmas's leftover idea is not on this year's list.
    $me->startsChristmas(2025);
    $me->addsTo('Christmas 2025', 'Mum');
    $me->addsGifts('Christmas 2025', 'Mum', gift('Teapot')->costing('30'));

    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->costing('80')->from('Petco')->at('https://petco.example/dog-bed'), gift('Chew rope'));
    $me->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->costing('40')->from('Target')->thatIs('Bought', by: 'Jason'));
    $partner->addsGifts('Christmas 2026', 'Mum', gift('Candle')->costing('15')->from('Etsy'));
    $partner->addsGifts('Christmas 2026', 'Grandad', gift('Socks')->costing('10')->thatIs('Wrapped', by: 'Sam'));

    // Only ideas are left to buy: by person, then in the order we thought of them.
    foreach ([$me, $partner] as $whoever) {
        expect($whoever->shoppingList('Christmas 2026'))
            ->toListIdeas([
                ['Mum', 'Candle', '$15', 'Etsy', ''],
                ['Pluto', 'Dog bed', '$80', 'Petco', 'https://petco.example/dog-bed'],
                ['Pluto', 'Chew rope', 'No price', '', ''],
            ])
            ->toShowIdeasLeft(3, '$95');
    }
});
