<?php

declare(strict_types=1);

it('lists everyone from last year\'s spreadsheet under their groups', function () {
    itIs('2026-10-27');
    $me = signsUp('Jason', 'jason@example.com');

    $me->adds(person('Mum')->in('Family')->born(month: 12, day: 2, year: 1960));
    $me->adds(person('Grandma')->in('Family')->born(month: 3, day: 14));
    $me->adds(person('The Smiths')->in('Friends')->shared());
    $me->adds(person('alex')->in('Friends'));
    $me->adds(person('Dave')->in('Coworkers'));
    $me->adds(person('Mrs Patel')->in('Coworkers'));

    // Mrs Patel is my daughter's teacher, not a coworker, and her birthday is a leap day.
    $me->changes('Mrs Patel', person('Mrs Patel')->in('Teachers')->born(month: 2, day: 29));

    expect($me->peopleList())
        ->toShowGroups([
            'Family' => ['Grandma', 'Mum'],
            'Friends' => ['alex', 'The Smiths'],
            'Teachers' => ['Mrs Patel'],
            'Coworkers' => ['Dave'],
        ])
        ->toShowSharedEntries(['The Smiths'])
        ->toShowBirthday('Mum', 'Dec 2, 1960', age: 65)
        ->toShowBirthday('Grandma', 'Mar 14', age: null)
        ->toShowBirthday('Mrs Patel', 'Feb 29', age: null)
        ->toShowBirthday('Dave', null, age: null);
});
