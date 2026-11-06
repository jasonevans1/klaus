<?php

declare(strict_types=1);

it('lists our occasions in one box with Delete tucked under More at the end of each line, still warning how many gifts go with it', function () {
    itIs('2026-11-20');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2025);
    $me->startsChristmas(2026);
    $me->addsTo('Christmas 2026', 'Pluto');
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->costing('80'), gift('Chew rope')->costing('12'));

    expect($me->occasionsPage())
        ->toListInOneBox(['Christmas 2026', 'Christmas 2025'])
        ->toLineUp('Christmas 2026', atHand: [], underMore: ['Delete' => 'Delete Christmas 2026 and its 2 gifts?'])
        ->toLineUp('Christmas 2025', atHand: [], underMore: ['Delete' => 'Delete Christmas 2025?']);

    $me->picksFromMore('Delete', for: 'Christmas 2025', on: 'Occasions');

    expect($me->occasions())->toBe(['Christmas 2026']);
});
