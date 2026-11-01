<?php

declare(strict_types=1);

it('puts my private gifts on my shopping list and wrapping checklist with a Private badge, counted for me but not for my partner', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Sam')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');

    $partner->addsGifts('Christmas 2026', 'Pluto',
        gift('Dog bed')->costing('80')->thatIs('Bought', by: 'Sam'),
        gift('Chew rope')->costing('12'),
    );
    $me->addsGifts('Christmas 2026', 'Sam',
        gift('Diving watch')->costing('149.99')->from('Seiko')->thatIs('Bought', by: 'Jason')->privately(),
        gift('Perfume')->costing('64')->from('Sephora')->privately(),
    );

    expect($me->shoppingList('Christmas 2026'))
        ->toListIdeas([
            ['Pluto', 'Chew rope', '$12', '', ''],
            ['Sam', 'Perfume', '$64', 'Sephora', ''],
        ])
        ->toBadgeAsPrivate([['Sam', 'Perfume']])
        ->toShowIdeasLeft(2, '$76');

    expect($me->wrappingChecklist('Christmas 2026'))
        ->toListGiftsToWrap([
            ['Pluto', 'Dog bed', 'to wrap'],
            ['Sam', 'Diving watch', 'to wrap'],
        ])
        ->toBadgeAsPrivate([['Sam', 'Diving watch']])
        ->toShowWrapped(0, of: 2);

    expect($partner->shoppingList('Christmas 2026'))
        ->toListIdeas([['Pluto', 'Chew rope', '$12', '', '']])
        ->toBadgeAsPrivate([])
        ->toShowIdeasLeft(1, '$12');

    expect($partner->wrappingChecklist('Christmas 2026'))
        ->toListGiftsToWrap([['Pluto', 'Dog bed', 'to wrap']])
        ->toBadgeAsPrivate([])
        ->toShowWrapped(0, of: 1);
});
