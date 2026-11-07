<?php

use App\Models\User;

it('builds initials from first and last name parts, uppercased', function () {
    expect((new User(['name' => 'john smith']))->initials())->toBe('JS');
    expect((new User(['name' => 'Mary Jane Watson']))->initials())->toBe('MW');
    expect((new User(['name' => 'Madonna']))->initials())->toBe('M');
});
