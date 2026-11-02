<?php

declare(strict_types=1);

it('tells me when I log in in late November about Pluto\'s birthday and who still needs a Christmas gift, until it is all sorted', function () {
    itIs('2026-11-28');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Pluto')->in('Family')->born(12, 10));
    $me->adds(person('Mum')->in('Family')->born(3, 14));
    $me->adds(person('Grandpa')->in('Family'));
    $me->adds(person('Uncle Bob')->in('Family')->born(12, 5));
    $me->archives('Uncle Bob');
    $me->startsChristmas(2026);
    $me->addsTo('Christmas 2026', 'Mum', 'Grandpa');

    // Mum's birthday is months away and Uncle Bob is archived, so neither is mentioned.
    expect($me->dashboard())
        ->toRemindOfBirthdays(['Pluto' => 12])
        ->toSayNoGiftYetForTheBirthdayOf([])
        ->toRemindOfPeopleWithNoGift(['Christmas 2026' => ['Grandpa', 'Mum']]);

    // Overnight, Klaus creates Pluto's birthday, and he has nothing for it yet.
    theDailyJobRuns();

    expect($me->dashboard())
        ->toRemindOfBirthdays(['Pluto' => 12])
        ->toSayNoGiftYetForTheBirthdayOf(['Pluto'])
        ->toRemindOfPeopleWithNoGift(['Christmas 2026' => ['Grandpa', 'Mum']]);

    $me->addsGifts('Christmas 2026', 'Mum', gift('Scarf')->costing('40'));

    expect($me->dashboard())->toRemindOfPeopleWithNoGift(['Christmas 2026' => ['Grandpa']]);

    $me->addsGifts('Christmas 2026', 'Grandpa', gift('Crossword book'));
    $me->addsGifts("Pluto's birthday 2026", 'Pluto', gift('Chew rope'));

    // Pluto's birthday is still coming, but nothing about it needs me any more.
    expect($me->dashboard())
        ->toRemindOfBirthdays(['Pluto' => 12])
        ->toSayNoGiftYetForTheBirthdayOf([])
        ->toRemindOfPeopleWithNoGift([]);

    itIs('2026-12-26');

    expect($me->dashboard())->toSayAllCaughtUp();
});
