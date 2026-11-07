<?php

declare(strict_types=1);

it('marks who has no gift yet right on their own line at Christmas, with no separate list, for both of us, until they get one', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Ms Lee')->in('Teachers'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Chew rope'));

    expect($me->occasion('Christmas 2026'))->toBadgeNoGiftYetOn(['Mum', 'Ms Lee']);
    expect($partner->occasion('Christmas 2026'))->toBadgeNoGiftYetOn(['Mum', 'Ms Lee']);

    $partner->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->costing('40'));

    expect($me->occasion('Christmas 2026'))->toBadgeNoGiftYetOn(['Ms Lee']);

    $me->addsGifts('Christmas 2026', 'Ms Lee', gift('Mug')->costing('12'));

    expect($me->occasion('Christmas 2026'))->toBadgeNoGiftYetOn([]);
});
