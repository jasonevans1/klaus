<?php

declare(strict_types=1);

it('does not skip a step when my page was out of date or I double-click', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsTo('Christmas 2026', 'Pluto');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed'));

    // I left Christmas open while Sam bought the dog bed, then I clicked Mark bought too.
    $myOldPage = $me->opens('Christmas 2026');
    $partner->advancesGift('Christmas 2026', 'Pluto', 'Dog bed');
    $myOldPage->advancesGift('Pluto', 'Dog bed');

    expect($me->occasion('Christmas 2026'))->toShowGiftStatus('Pluto', 'Dog bed', 'Bought', boughtBy: 'Sam');

    // A double-click on Mark wrapped wraps it once; it is not given yet.
    $me->doubleClicksAdvance('Christmas 2026', 'Pluto', 'Dog bed');

    expect($partner->occasion('Christmas 2026'))->toShowGiftStatus('Pluto', 'Dog bed', 'Wrapped', boughtBy: 'Sam');
});
