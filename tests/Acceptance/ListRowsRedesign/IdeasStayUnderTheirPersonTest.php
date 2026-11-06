<?php

declare(strict_types=1);

it('keeps each person\'s ideas under them in their group\'s box, with Edit at the end of every idea and Delete tucked under More, still asking first', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Uncle Bob')->in('Friends'));

    $me->addsIdeas('Mum', gift('Scarf')->costing('25'));
    $me->addsIdeas('Pluto', gift('Dog bed')->costing('80')->from('Petco'), gift('Chew rope')->costing('12'));

    expect($me->ideasPage())
        ->toListInBoxes(['Family' => ['Mum', 'Pluto'], 'Friends' => ['Uncle Bob']])
        ->toLineUp('Mum', atHand: ['Add idea'], underMore: [])
        ->toLineUp('Uncle Bob', atHand: ['Add idea'], underMore: [])
        ->toLineUp('Scarf', atHand: ['Edit'], underMore: ['Delete' => 'Delete Scarf?'])
        ->toLineUp('Chew rope', atHand: ['Edit'], underMore: ['Delete' => 'Delete Chew rope?']);

    // Mum and Pluto share the Family box, but each keeps only their own ideas.
    expect($me->ideaList())->toShowIdeas([
        'Mum' => [['Scarf', '$25', '', '']],
        'Pluto' => [['Dog bed', '$80', 'Petco', ''], ['Chew rope', '$12', '', '']],
        'Uncle Bob' => [],
    ]);

    $me->picksFromMore('Delete', for: 'Chew rope', on: 'Ideas');

    expect($me->ideaList())->toShowIdeas([
        'Mum' => [['Scarf', '$25', '', '']],
        'Pluto' => [['Dog bed', '$80', 'Petco', '']],
        'Uncle Bob' => [],
    ]);
});
