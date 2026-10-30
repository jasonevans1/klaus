<?php

declare(strict_types=1);

it('lists each of Pluto\'s eight gifts as its own row under Pluto, whichever of us added it', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Dave')->in('Coworkers'));
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');

    $me->addsGifts('Christmas 2026', 'Pluto',
        gift('Chew rope')->costing('12.99')->from('Petco')->at('https://petco.example/chew-rope'),
        gift('Squeaky duck')->costing('6.5')->from('Petco'),
        gift('Dog bed')->costing('1234.56')->from('Chewy')->at('https://chewy.example/dog-bed'),
        gift('Bully sticks')->costing('25'),
        gift('Stocking treats'),
    );
    $partner->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->costing('40')->from('Target'));
    $partner->addsGifts('Christmas 2026', 'Pluto',
        gift('Tennis balls')->costing('9.99')->from('Target'),
        gift('Reindeer antlers')->costing('0.99'),
        gift('Name tag')->at('https://tags.example/pluto'),
    );

    $christmas = [
        'Mum' => [
            ['Scarf', '$40', 'Target', ''],
        ],
        'Pluto' => [
            ['Chew rope', '$12.99', 'Petco', 'https://petco.example/chew-rope'],
            ['Squeaky duck', '$6.50', 'Petco', ''],
            ['Dog bed', '$1,234.56', 'Chewy', 'https://chewy.example/dog-bed'],
            ['Bully sticks', '$25', '', ''],
            ['Stocking treats', 'No price', '', ''],
            ['Tennis balls', '$9.99', 'Target', ''],
            ['Reindeer antlers', '$0.99', '', ''],
            ['Name tag', 'No price', '', 'https://tags.example/pluto'],
        ],
        'Dave' => [],
    ];

    expect($me->occasion('Christmas 2026'))->toShowGifts($christmas);
    expect($partner->occasion('Christmas 2026'))->toShowGifts($christmas);
});
