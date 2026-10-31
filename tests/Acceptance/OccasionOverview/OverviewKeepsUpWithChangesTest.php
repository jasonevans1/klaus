<?php

declare(strict_types=1);

it('updates the numbers on the page I have open as soon as I buy a gift, change a budget, delete a gift or remove someone', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('The Smiths')->in('Friends')->shared());
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Pluto', 100);
    $me->setsBudget('Christmas 2026', 'Mum', 50);
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->costing('80')->thatIs('Bought', by: 'Jason'));
    $me->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->costing('62.50'));
    $me->addsGifts('Christmas 2026', 'The Smiths', gift('Wine')->costing('25')->thatIs('Bought', by: 'Jason'));

    $page = $me->opens('Christmas 2026');

    expect($page->shows())
        ->toShowSpending(['Mum' => ['$0', '$50'], 'Pluto' => ['$80', '$20'], 'The Smiths' => ['$25', null]])
        ->toShowSeasonTotals(budget: '$150', spent: '$105', remaining: '$45');

    // Buying the scarf puts Mum over budget.
    $page->advancesGift('Mum', 'Scarf');

    expect($page->shows())
        ->toShowSpending(['Mum' => ['$62.50', '-$12.50'], 'Pluto' => ['$80', '$20'], 'The Smiths' => ['$25', null]])
        ->toShowSeasonTotals(budget: '$150', spent: '$167.50', remaining: '-$17.50');

    // Raising her budget brings her back under.
    $page->setsBudget('Mum', 70);

    expect($page->shows())
        ->toShowSpending(['Mum' => ['$62.50', '$7.50'], 'Pluto' => ['$80', '$20'], 'The Smiths' => ['$25', null]])
        ->toShowSeasonTotals(budget: '$170', spent: '$167.50', remaining: '$2.50');

    // The dog bed goes back to the shop.
    $page->deletesGift('Pluto', 'Dog bed');

    expect($page->shows())
        ->toShowSpending(['Mum' => ['$62.50', '$7.50'], 'Pluto' => ['$0', '$100'], 'The Smiths' => ['$25', null]])
        ->toShowSeasonTotals(budget: '$170', spent: '$87.50', remaining: '$82.50');

    // The Smiths are off the list this year, and their wine with them.
    $page->removes('The Smiths');

    expect($page->shows())
        ->toShowSpending(['Mum' => ['$62.50', '$7.50'], 'Pluto' => ['$0', '$100']])
        ->toShowSeasonTotals(budget: '$170', spent: '$62.50', remaining: '$107.50');
});
