<?php

declare(strict_types=1);

it('tells me in mid December whose birthday has no gift yet on that year\'s Birthdays, counting my partner\'s secret gift only for them', function () {
    itIs('2026-12-15');
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    $me->adds(person('Pluto')->in('Family')->born(12, 20));
    $me->adds(person('Mum')->in('Family')->born(1, 3));
    $me->adds(person('Grandpa')->in('Family'));

    $me->setsUpBirthdays(2026);
    $me->setsUpBirthdays(2027);
    $me->startsChristmas(2026);
    $me->addsTo('Christmas 2026', 'Grandpa');

    // Mum's gift was for last January's birthday, not the one coming up.
    $me->addsGifts('Birthdays 2026', 'Mum', gift('Scarf')->costing('30'));
    $partner->addsGifts('Birthdays 2026', 'Pluto', gift('Squeaky duck')->costing('10')->privately());

    // Birthdays 2027 starts on January 1, but it is not an occasion with people still waiting for a gift.
    expect($me->dashboard())
        ->toRemindOfBirthdays(['Pluto' => 5, 'Mum' => 19])
        ->toSayNoGiftYetForTheBirthdayOf(['Pluto', 'Mum'])
        ->toRemindOfPeopleWithNoGift(['Christmas 2026' => ['Grandpa']])
        ->toShowNoTraceOf('Squeaky duck');

    // My partner knows they have Pluto covered.
    expect($partner->dashboard())
        ->toRemindOfBirthdays(['Pluto' => 5, 'Mum' => 19])
        ->toSayNoGiftYetForTheBirthdayOf(['Mum']);

    $me->addsGifts('Birthdays 2026', 'Pluto', gift('Chew rope'));
    $me->addsGifts('Birthdays 2027', 'Mum', gift('Gardening gloves'));

    expect($me->dashboard())
        ->toRemindOfBirthdays(['Pluto' => 5, 'Mum' => 19])
        ->toSayNoGiftYetForTheBirthdayOf([])
        ->toRemindOfPeopleWithNoGift(['Christmas 2026' => ['Grandpa']]);
});
