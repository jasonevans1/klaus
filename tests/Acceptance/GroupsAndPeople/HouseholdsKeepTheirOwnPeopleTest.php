<?php

declare(strict_types=1);

it('shows the people my partner enters to me but never to another household', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->invites('sam@example.com');
    $partner = acceptsInvite(to: 'sam@example.com', name: 'Sam');
    $neighbour = signsUp('Ned', 'ned@example.com');

    $partner->adds(person('Grandma')->in('Family'));
    $partner->adds(person('The Smiths')->in('Friends')->shared());
    $neighbour->adds(person('Gran')->in('Family'));

    expect($me->peopleList())->toShowGroups([
        'Family' => ['Grandma'],
        'Friends' => ['The Smiths'],
    ]);
    expect($neighbour->peopleListIncludingArchived())->toShowGroups([
        'Family' => ['Gran'],
    ]);
});
