<?php

declare(strict_types=1);

it('lists Pluto\'s birthday as coming up with what we have spent on it, but not Christmas, which has its own card, or Grandpa\'s birthday that has passed', function () {
    itIs('2026-10-01');
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Grandpa')->in('Family')->born(10, 20));
    $me->adds(person('Pluto')->in('Family')->born(12, 10));
    $me->startsChristmas(2026);

    // Grandpa's birthday is created in October and comes and goes.
    theDailyJobRuns();

    itIs('2026-11-28');
    theDailyJobRuns();

    $me->setsBudget("Pluto's birthday 2026", 'Pluto', 50);
    $me->addsGifts("Pluto's birthday 2026", 'Pluto', gift('Chew rope')->costing('25')->thatIs('Bought', by: 'Jason'));
    $partner->addsGifts("Pluto's birthday 2026", 'Pluto', gift('Squeaky duck')->costing('10')->thatIs('Bought', by: 'Sam')->privately());

    expect($me->dashboard())
        ->toListComingUp(["Pluto's birthday 2026" => ['in 12 days (Dec 10)', '$25 of $50 spent']])
        ->toShowNoTraceOf('Squeaky duck');
});
