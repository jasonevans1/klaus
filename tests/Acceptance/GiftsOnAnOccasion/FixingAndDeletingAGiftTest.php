<?php

declare(strict_types=1);

it('changes a gift in place and deletes a gift we no longer want, for both of us', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->startsChristmas(2026);
    $me->addsTo('Christmas 2026', 'Pluto');
    $me->addsGifts('Christmas 2026', 'Pluto',
        gift('Chew rope')->costing('12.99')->from('Petco'),
        gift('Squeaky duck')->costing('6.50'),
        gift('Dog bed')->costing('80')->from('Chewy'),
    );

    // Sam found the rope cheaper elsewhere and decided against the duck.
    $partner->changesGift('Christmas 2026', 'Pluto', 'Chew rope',
        gift('Chew rope, large')->costing('9.49')->from('Chewy')->at('https://chewy.example/rope'),
    );
    $partner->deletesGift('Christmas 2026', 'Pluto', 'Squeaky duck');

    expect($me->occasion('Christmas 2026'))->toShowGifts([
        'Pluto' => [
            ['Chew rope, large', '$9.49', 'Chewy', 'https://chewy.example/rope'],
            ['Dog bed', '$80', 'Chewy', ''],
        ],
    ]);

    // Clearing the price, store and link leaves just the idea.
    $me->changesGift('Christmas 2026', 'Pluto', 'Dog bed', gift('Dog bed'));

    expect($partner->occasion('Christmas 2026'))->toShowGifts([
        'Pluto' => [
            ['Chew rope, large', '$9.49', 'Chewy', 'https://chewy.example/rope'],
            ['Dog bed', 'No price', '', ''],
        ],
    ]);
});
