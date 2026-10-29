<?php

declare(strict_types=1);

use App\Enums\RecipientGroup;

it('lists the four groups in display order with labels', function () {
    expect(array_map(fn (RecipientGroup $g) => [$g->value, $g->label()], RecipientGroup::cases()))
        ->toBe([
            ['family', 'Family'],
            ['friends', 'Friends'],
            ['teachers', 'Teachers'],
            ['coworkers', 'Coworkers'],
        ]);
});
