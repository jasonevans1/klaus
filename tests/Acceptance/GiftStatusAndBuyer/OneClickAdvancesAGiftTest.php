<?php

declare(strict_types=1);

it('moves a gift one step per click from idea to given, and shows my partner as the buyer once they mark it bought', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsTo('Christmas 2026', 'Pluto');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->costing('80'), gift('Chew rope'));

    expect($me->occasion('Christmas 2026'))
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Idea')
        ->toShowGiftStatus('Pluto', 'Chew rope', 'Idea');

    // Sam buys the dog bed: whoever marks it bought is the buyer.
    $partner->advancesGift('Christmas 2026', 'Pluto', 'Dog bed');

    expect($me->occasion('Christmas 2026'))
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Bought', boughtBy: 'Sam')
        ->toShowGiftStatus('Pluto', 'Chew rope', 'Idea');

    // I wrap it and give it; Sam stays the one who bought it.
    $me->advancesGift('Christmas 2026', 'Pluto', 'Dog bed');

    expect($partner->occasion('Christmas 2026'))->toShowGiftStatus('Pluto', 'Dog bed', 'Wrapped', boughtBy: 'Sam');

    $me->advancesGift('Christmas 2026', 'Pluto', 'Dog bed');

    // Given is the last step: there is nothing more to click.
    expect($partner->occasion('Christmas 2026'))->toShowGiftStatus('Pluto', 'Dog bed', 'Given', boughtBy: 'Sam');
    expect($me->occasion('Christmas 2026'))->toShowGifts([
        'Pluto' => [['Dog bed', '$80', '', ''], ['Chew rope', 'No price', '', '']],
    ]);
});
