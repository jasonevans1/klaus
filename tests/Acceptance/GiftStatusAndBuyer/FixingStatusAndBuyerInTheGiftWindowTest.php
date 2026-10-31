<?php

declare(strict_types=1);

it('lets either of us set the status and buyer in the gift window, and forgets the buyer when a gift goes back to an idea', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');

    // Sam enters a scarf I already bought last week.
    $partner->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->costing('40')->from('Target')->thatIs('Bought', by: 'Jason'));
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed'));

    expect($me->occasion('Christmas 2026'))
        ->toShowGiftStatus('Mum', 'Scarf', 'Bought', boughtBy: 'Jason')
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Idea');

    // I clicked Mark bought on the dog bed by mistake and set it back to an idea.
    $me->advancesGift('Christmas 2026', 'Pluto', 'Dog bed');
    $me->setsGiftStatus('Christmas 2026', 'Pluto', 'Dog bed', 'Idea');

    // It turns out Sam bought the scarf, and has wrapped it.
    $partner->setsGiftStatus('Christmas 2026', 'Mum', 'Scarf', 'Wrapped', by: 'Sam');

    expect($me->occasion('Christmas 2026'))
        ->toShowGiftStatus('Mum', 'Scarf', 'Wrapped', boughtBy: 'Sam')
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Idea')
        ->toShowGifts([
            'Mum' => [['Scarf', '$40', 'Target', '']],
            'Pluto' => [['Dog bed', 'No price', '', '']],
        ]);
});
