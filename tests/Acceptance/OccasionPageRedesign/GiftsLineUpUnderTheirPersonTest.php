<?php

declare(strict_types=1);

it('lines Pluto\'s Christmas gifts up under him with the next step at hand and Edit and Delete tucked under More, and folds his year-round ideas away until I open them', function () {
    itIs('2026-03-14');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Pluto')->in('Family'));
    $me->addsIdeas('Pluto', gift('Squeaky ball')->costing('6'), gift('Dog coat')->costing('35'));

    itIs('2026-11-20');
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->addsGifts('Christmas 2026', 'Pluto',
        gift('Dog bed')->costing('80')->from('Petco'),
        gift('Chew rope')->costing('12')->thatIs('Given', by: 'Jason'),
    );

    expect($me->occasionPage('Christmas 2026'))
        ->toListInBoxes(['Family' => ['Mum', 'Pluto']])
        ->toLineUp('Dog bed', atHand: ['Mark bought'], underMore: ['Edit' => null, 'Delete' => 'Delete Dog bed?'])
        ->toLineUp('Chew rope', atHand: [], underMore: ['Edit' => null, 'Delete' => 'Delete Chew rope?']);

    expect($me->occasion('Christmas 2026'))
        ->toShowGifts(['Mum' => [], 'Pluto' => [['Dog bed', '$80', 'Petco', ''], ['Chew rope', '$12', '', '']]])
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Idea')
        ->toShowGiftStatus('Pluto', 'Chew rope', 'Given', boughtBy: 'Jason')
        ->toFoldYearRoundIdeasBehind(['Mum' => null, 'Pluto' => 'Year-round ideas (2)'])
        ->toShowYearRoundIdeas(['Mum' => [], 'Pluto' => [['Squeaky ball', '$6'], ['Dog coat', '$35']]]);

    // The one-click step and the More menu still do their jobs.
    $me->advancesGift('Christmas 2026', 'Pluto', 'Dog bed');
    $me->picksFromMore('Delete', for: 'Chew rope', on: 'Christmas 2026');
    $me->addsIdeaTo('Christmas 2026', for: 'Pluto', title: 'Dog coat');

    expect($me->occasion('Christmas 2026'))
        ->toShowGifts(['Mum' => [], 'Pluto' => [['Dog coat', '$35', '', ''], ['Dog bed', '$80', 'Petco', '']]])
        ->toShowGiftStatus('Pluto', 'Dog bed', 'Bought', boughtBy: 'Jason')
        ->toFoldYearRoundIdeasBehind(['Mum' => null, 'Pluto' => 'Year-round ideas (1)']);
});
