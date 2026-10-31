<?php

declare(strict_types=1);

it('ticks a gift off the list as soon as either of us marks it bought, and remembers who bought it', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->costing('80'), gift('Chew rope')->costing('15'));
    $me->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->costing('40'));

    // Sam is at the pet shop.
    $samsList = $partner->opensShoppingList('Christmas 2026');
    $samsList->marksBought('Pluto', 'Dog bed');

    expect($samsList->shows())
        ->toListIdeas([
            ['Mum', 'Scarf', '$40', '', ''],
            ['Pluto', 'Chew rope', '$15', '', ''],
        ])
        ->toShowIdeasLeft(2, '$55');

    // I pick up the rest.
    $myList = $me->opensShoppingList('Christmas 2026');
    $myList->marksBought('Mum', 'Scarf');
    $myList->marksBought('Pluto', 'Chew rope');

    expect($myList->shows())->toListIdeas([])->toShowIdeasLeft(0, '$0');

    expect($partner->occasion('Christmas 2026'))
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Bought', boughtBy: 'Sam')
        ->toShowGiftStatus('Pluto', 'Chew rope', 'Bought', boughtBy: 'Jason')
        ->toShowGiftStatus('Mum', 'Scarf', 'Bought', boughtBy: 'Jason');
});
