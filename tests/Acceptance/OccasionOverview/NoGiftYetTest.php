<?php

declare(strict_types=1);

it('lists who has no gift yet, where even an idea counts as a gift, until everyone has one', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Grandpa')->in('Family'));
    $me->adds(person('Ms Lee')->in('Teachers'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');

    $me->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->costing('40')->thatIs('Bought', by: 'Jason'));
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Chew rope'));

    expect($me->occasion('Christmas 2026'))->toShowNoGiftYet(['Grandpa', 'Ms Lee']);
    expect($partner->occasion('Christmas 2026'))->toShowNoGiftYet(['Grandpa', 'Ms Lee']);

    $partner->addsGifts('Christmas 2026', 'Grandpa', gift('Crossword book'));

    expect($me->occasion('Christmas 2026'))->toShowNoGiftYet(['Ms Lee']);

    $me->addsGifts('Christmas 2026', 'Ms Lee', gift('Mug')->costing('12'));

    expect($me->occasion('Christmas 2026'))->toShowNoGiftYet([]);
});
