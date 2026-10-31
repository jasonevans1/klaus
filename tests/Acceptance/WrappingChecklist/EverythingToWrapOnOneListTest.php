<?php

declare(strict_types=1);

it('shows both of us every bought and wrapped gift for Christmas, for everyone, on one wrapping list', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Grandad')->in('Family'));

    // Last Christmas's teapot was bought but never given; it is not on this year's list.
    $me->startsChristmas(2025);
    $me->addsTo('Christmas 2025', 'Mum');
    $me->addsGifts('Christmas 2025', 'Mum', gift('Teapot')->thatIs('Bought', by: 'Jason'));

    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->thatIs('Bought', by: 'Jason'), gift('Chew rope'), gift('Ball')->thatIs('Wrapped', by: 'Jason'));
    $partner->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->thatIs('Bought', by: 'Sam'), gift('Gloves')->thatIs('Given', by: 'Sam'));
    $partner->addsGifts('Christmas 2026', 'Grandad', gift('Socks')->thatIs('Bought', by: 'Jason'));

    // Ideas and gifts already given are left out: by person, then in the order we added them.
    foreach ([$me, $partner] as $whoever) {
        expect($whoever->wrappingChecklist('Christmas 2026'))
            ->toListGiftsToWrap([
                ['Grandad', 'Socks', 'to wrap'],
                ['Mum', 'Scarf', 'to wrap'],
                ['Pluto', 'Dog bed', 'to wrap'],
                ['Pluto', 'Ball', 'wrapped'],
            ])
            ->toShowWrapped(1, of: 4);
    }
});
