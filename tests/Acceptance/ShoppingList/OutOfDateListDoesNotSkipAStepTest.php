<?php

declare(strict_types=1);

it('leaves a gift bought by my partner when I mark it bought on my out-of-date list', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsTo('Christmas 2026', 'Pluto');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->costing('80'));

    // We both had the list open in different shops; Sam got there first.
    $myList = $me->opensShoppingList('Christmas 2026');
    $partner->opensShoppingList('Christmas 2026')->marksBought('Pluto', 'Dog bed');
    $myList->marksBought('Pluto', 'Dog bed');

    // It is bought, by Sam, and not wrapped.
    expect($me->occasion('Christmas 2026'))->toShowGiftStatus('Pluto', 'Dog bed', 'Bought', boughtBy: 'Sam');
    expect($me->shoppingList('Christmas 2026'))->toListIdeas([])->toShowIdeasLeft(0, '$0');
});
