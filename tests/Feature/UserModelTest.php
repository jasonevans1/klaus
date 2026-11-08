<?php

use App\Models\User;

it('mass assigns name, email and password only', function () {
    $user = new User([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'secret-pass',
        'remember_token' => 'tok',
    ]);

    expect($user->name)->toBe('Jane')
        ->and($user->email)->toBe('jane@example.com')
        ->and($user->password)->not->toBeNull()
        ->and($user->remember_token)->toBeNull();
});

it('hides sensitive attributes when serialised', function () {
    $user = new User;
    $user->forceFill([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'secret-pass',
        'two_factor_secret' => 'a',
        'two_factor_recovery_codes' => 'b',
        'remember_token' => 'c',
    ]);

    $array = $user->toArray();

    expect($array)->toHaveKeys(['name', 'email'])
        ->and($array)->not->toHaveKeys(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token']);
});
