<?php

declare(strict_types=1);

it('shows each group of people in its own box, with Edit at the end of every line however long the name, and Archive or Restore tucked under More', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Bo')->in('Family'));
    $me->adds(person('Great-Aunt Margaret-Rose Fotheringham-Smythe')->in('Family'));
    $me->adds(person('The Smiths')->in('Friends')->shared());
    $me->archives('Bo');

    expect($me->peoplePage(includingArchived: true))
        ->toListInBoxes([
            'Family' => ['Bo', 'Great-Aunt Margaret-Rose Fotheringham-Smythe'],
            'Friends' => ['The Smiths'],
        ])
        ->toLineUp('Bo', atHand: ['Edit'], underMore: ['Restore' => null])
        ->toLineUp('Great-Aunt Margaret-Rose Fotheringham-Smythe', atHand: ['Edit'], underMore: ['Archive' => null])
        ->toLineUp('The Smiths', atHand: ['Edit'], underMore: ['Archive' => null]);

    // Archiving and restoring from the More menu still work.
    $me->picksFromMore('Restore', for: 'Bo', on: 'People');
    $me->picksFromMore('Archive', for: 'The Smiths', on: 'People');

    expect($me->peopleList())->toShowGroups(['Family' => ['Bo', 'Great-Aunt Margaret-Rose Fotheringham-Smythe']]);
    expect($me->peopleListIncludingArchived())->toShowArchived(['The Smiths']);
});
