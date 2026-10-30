<?php

declare(strict_types=1);

it('warns how many gifts go with a person or a whole Christmas, and deletes only those gifts', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));

    $me->startsChristmas(2025);
    $me->addsTo('Christmas 2025', 'Pluto');
    $me->addsGifts('Christmas 2025', 'Pluto', gift('Bone')->costing('5'));

    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->addsGifts('Christmas 2026', 'Mum', gift('Scarf'), gift('Candle'));
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Chew rope'), gift('Dog bed'), gift('Tennis balls'));

    expect($me->warningWhenRemoving('Mum', from: 'Christmas 2026'))->toBe('Remove Mum and their 2 gifts from this occasion?');
    expect($me->warningWhenRemoving('Pluto', from: 'Christmas 2026'))->toBe('Remove Pluto and their 3 gifts from this occasion?');
    expect($me->warningWhenRemoving('Pluto', from: 'Christmas 2025'))->toBe('Remove Pluto and their 1 gift from this occasion?');

    $me->removesFrom('Christmas 2026', 'Pluto');
    // Putting Pluto back does not bring the deleted gifts back.
    $me->addsTo('Christmas 2026', 'Pluto');

    expect($me->occasion('Christmas 2026'))->toShowGifts([
        'Mum' => [['Scarf', 'No price', '', ''], ['Candle', 'No price', '', '']],
        'Pluto' => [],
    ]);
    expect($me->warningWhenRemoving('Pluto', from: 'Christmas 2026'))->toBe('Remove Pluto from this occasion?');
    expect($me->warningWhenDeleting('Christmas 2026'))->toBe('Delete Christmas 2026 and its 2 gifts?');
    expect($me->warningWhenDeleting('Christmas 2025'))->toBe('Delete Christmas 2025 and its 1 gift?');

    $me->deletesOccasion('Christmas 2026');

    expect($me->occasions())->toBe(['Christmas 2025']);
    expect($me->occasion('Christmas 2025'))->toShowGifts([
        'Pluto' => [['Bone', '$5', '', '']],
    ]);
});
