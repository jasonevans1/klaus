<?php

declare(strict_types=1);

it('shows what we have spent and have left for each person and for the whole of Christmas, counting only gifts already bought', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family'));
    $me->adds(person('Mum')->in('Family'));
    $me->adds(person('Grandpa')->in('Family'));
    $me->adds(person('The Smiths')->in('Friends')->shared());
    $me->startsChristmas(2026);
    $me->addsEveryoneTo('Christmas 2026');
    $me->setsBudget('Christmas 2026', 'Pluto', 100);
    $me->setsBudget('Christmas 2026', 'Mum', 50);
    $me->setsBudget('Christmas 2026', 'Grandpa', 30);
    // The Smiths have no budget this year.

    // Pluto: Sam bought the dog bed; the chew rope is still only an idea, so it is not spent.
    $me->addsGifts('Christmas 2026', 'Pluto', gift('Dog bed')->costing('80'), gift('Chew rope')->costing('12.99'));
    $partner->advancesGift('Christmas 2026', 'Pluto', 'Dog bed');
    // Mum: one gift wrapped, one already given, together more than her budget.
    $me->addsGifts('Christmas 2026', 'Mum',
        gift('Scarf')->costing('40')->thatIs('Wrapped', by: 'Jason'),
        gift('Candle')->costing('22.50')->thatIs('Given', by: 'Sam'),
    );
    // The Smiths: a bottle of wine, bought.
    $me->addsGifts('Christmas 2026', 'The Smiths', gift('Wine')->costing('25')->thatIs('Bought', by: 'Jason'));
    // Grandpa: nothing yet.

    foreach ([$me, $partner] as $either) {
        expect($either->occasion('Christmas 2026'))
            ->toShowPeopleWithBudgets([
                'Family' => ['Grandpa' => '$30', 'Mum' => '$50', 'Pluto' => '$100'],
                'Friends' => ['The Smiths' => 'No budget'],
            ])
            ->toShowSpending([
                'Grandpa' => ['$0', '$30'],
                'Mum' => ['$62.50', '-$12.50'],
                'Pluto' => ['$80', '$20'],
                'The Smiths' => ['$25', null],
            ])
            // Spending on The Smiths counts against the season even though they have no budget.
            ->toShowSeasonTotals(budget: '$180', spent: '$167.50', remaining: '$12.50');
    }
});
