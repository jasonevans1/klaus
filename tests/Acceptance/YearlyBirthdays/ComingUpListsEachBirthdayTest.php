<?php

declare(strict_types=1);

it('lists Pluto\'s and Mum\'s birthdays as coming up with what we have spent on each, but not the Birthdays occasions themselves, Christmas, or a birthday that has passed', function () {
    itIs('2026-11-28');
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Grandpa')->in('Family')->born(10, 20));
    $me->adds(person('Pluto')->in('Family')->born(12, 10));
    $me->adds(person('Mum')->in('Family')->born(1, 15));
    $me->startsChristmas(2026);
    $me->setsUpBirthdays(2026);
    $me->setsUpBirthdays(2027);

    $me->setsBudget('Birthdays 2026', 'Pluto', 50);
    $me->addsGifts('Birthdays 2026', 'Pluto', gift('Chew rope')->costing('25')->thatIs('Bought', by: 'Jason'));
    $partner->addsGifts('Birthdays 2026', 'Pluto', gift('Squeaky duck')->costing('10')->thatIs('Bought', by: 'Sam')->privately());

    // Mum's birthday in January belongs to Birthdays 2027, not to last January's.
    $me->setsBudget('Birthdays 2026', 'Mum', 60);
    $me->addsGifts('Birthdays 2026', 'Mum', gift('Scarf')->costing('45'));
    $me->setsBudget('Birthdays 2027', 'Mum', 30);

    expect($me->dashboard())
        ->toListComingUp([
            "Pluto's birthday" => ['in 12 days (Dec 10)', '$25 of $50 spent'],
            "Mum's birthday" => ['in 48 days (Jan 15)', '$0 of $30 spent'],
        ])
        ->toShowNoTraceOf('Squeaky duck');
});
