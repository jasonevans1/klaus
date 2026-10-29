<?php

declare(strict_types=1);

it('hides archived people until I bring them back, even when everyone is archived', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Uncle Bob')->in('Family'));
    $me->adds(person('Aunt Sue')->in('Family'));

    $me->archives('Uncle Bob');

    expect($me->peopleList())->toShowGroups(['Family' => ['Aunt Sue']]);
    expect($me->peopleListIncludingArchived())
        ->toShowGroups(['Family' => ['Aunt Sue', 'Uncle Bob']])
        ->toShowArchived(['Uncle Bob']);

    $me->archives('Aunt Sue');

    expect($me->peopleList())->toShowGroups([]);

    $me->restores('Uncle Bob');

    expect($me->peopleList())->toShowGroups(['Family' => ['Uncle Bob']]);
    expect($me->peopleListIncludingArchived())->toShowArchived(['Aunt Sue']);
});
