<?php

declare(strict_types=1);

use App\Enums\OccasionType;

it('lists the occasion types with labels', function () {
    expect(array_map(fn (OccasionType $t) => [$t->value, $t->label()], OccasionType::cases()))
        ->toBe([
            ['christmas', 'Christmas'],
            ['birthday', 'Birthday'],
        ]);
});
