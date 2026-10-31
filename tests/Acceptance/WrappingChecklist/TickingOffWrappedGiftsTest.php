<?php

declare(strict_types=1);

it('ticks a gift off as wrapped for both of us, unticks a slip back to bought, and keeps who bought it', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->thatIs('Bought', by: 'Jason'), gift('Chew rope')->thatIs('Bought', by: 'Sam'));
    $me->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->thatIs('Bought', by: 'Sam'));

    // Wrapping night: Sam wraps the dog bed I bought.
    $samsList = $partner->opensWrappingChecklist('Christmas 2026');
    $samsList->ticks('Pluto', 'Dog bed');

    expect($samsList->shows())
        ->toListGiftsToWrap([
            ['Mum', 'Scarf', 'to wrap'],
            ['Pluto', 'Dog bed', 'wrapped'],
            ['Pluto', 'Chew rope', 'to wrap'],
        ])
        ->toShowWrapped(1, of: 3);

    // I wrap the scarf, tick the chew rope by mistake and untick it again.
    $myList = $me->opensWrappingChecklist('Christmas 2026');
    $myList->ticks('Mum', 'Scarf');
    $myList->ticks('Pluto', 'Chew rope');
    $myList->unticks('Pluto', 'Chew rope');

    expect($myList->shows())
        ->toListGiftsToWrap([
            ['Mum', 'Scarf', 'wrapped'],
            ['Pluto', 'Dog bed', 'wrapped'],
            ['Pluto', 'Chew rope', 'to wrap'],
        ])
        ->toShowWrapped(2, of: 3);

    expect($partner->wrappingChecklist('Christmas 2026'))->toShowWrapped(2, of: 3);

    expect($partner->occasion('Christmas 2026'))
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Wrapped', boughtBy: 'Jason')
        ->toShowGiftStatus('Pluto', 'Chew rope', 'Bought', boughtBy: 'Sam')
        ->toShowGiftStatus('Mum', 'Scarf', 'Wrapped', boughtBy: 'Sam');
});
