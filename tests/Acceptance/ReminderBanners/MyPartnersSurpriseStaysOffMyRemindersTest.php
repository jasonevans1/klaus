<?php

declare(strict_types=1);

it('still tells me Mum has no Christmas gift when only my partner has a secret one for her, and never names it', function () {
    itIs('2026-11-28');
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');

    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->costing('80'));
    $partner->addsGifts('Christmas 2026', 'Mum', gift('Diving watch')->costing('149.99')->from('Seiko')->privately());

    expect($me->dashboard())
        ->toRemindOfPeopleWithNoGift(['Christmas 2026' => ['Mum']])
        ->toShowNoTraceOf('Diving watch', 'Seiko', '149.99');

    // My partner knows they have Mum covered.
    expect($partner->dashboard())->toSayAllCaughtUp();
});
