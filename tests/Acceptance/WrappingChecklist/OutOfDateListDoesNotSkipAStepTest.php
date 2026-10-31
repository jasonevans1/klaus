<?php

declare(strict_types=1);

it('never gives, unwraps or un-gives a gift because of a double click or an out-of-date wrapping list', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->thatIs('Bought', by: 'Jason'), gift('Ball')->thatIs('Wrapped', by: 'Jason'));
    $me->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->thatIs('Bought', by: 'Sam'));

    // I double-click the dog bed's tick: it is wrapped, not given and not unwrapped.
    $myList = $me->opensWrappingChecklist('Christmas 2026');
    $myList->ticks('Pluto', 'Dog bed', times: 2);

    // We both had the list open; Sam wraps the scarf and hands Pluto his ball before my list catches up.
    $myList = $me->opensWrappingChecklist('Christmas 2026');
    $partner->opensWrappingChecklist('Christmas 2026')->ticks('Mum', 'Scarf');
    $partner->advancesGift('Christmas 2026', 'Pluto', 'Ball');

    // My list still shows the scarf to wrap and the ball wrapped.
    $myList->ticks('Mum', 'Scarf');
    $myList->unticks('Pluto', 'Ball');

    expect($me->occasion('Christmas 2026'))
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Wrapped', boughtBy: 'Jason')
        ->toShowGiftStatus('Pluto', 'Ball', 'Given', boughtBy: 'Jason')
        ->toShowGiftStatus('Mum', 'Scarf', 'Wrapped', boughtBy: 'Sam');

    expect($myList->shows())
        ->toListGiftsToWrap([
            ['Mum', 'Scarf', 'wrapped'],
            ['Pluto', 'Dog bed', 'wrapped'],
        ])
        ->toShowWrapped(2, of: 2);
});
