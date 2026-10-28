<?php

use App\Models\Household;
use App\Models\User;

function register(string $name = 'John Doe', string $email = 'test@example.com', string $password = 'password')
{
    return test()->post(route('register.store'), [
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

it('creates a household when someone registers without an invite', function () {
    register();

    expect(Household::count())->toBe(1);
});

it('names the new household after the registering user', function () {
    register('Jane Roe');

    expect(Household::first()->name)->toBe("Jane Roe's household");
});

it('makes the registering user a member of the new household', function () {
    register();

    expect(User::first()->household_id)->toBe(Household::first()->id);
});

it('gives two separate sign-ups two separate households', function () {
    register('One', 'one@example.com');
    auth()->logout();
    register('Two', 'two@example.com');

    expect(Household::count())->toBe(2)
        ->and(User::pluck('household_id')->unique())->toHaveCount(2);
});

it('creates no household when registration fails validation', function () {
    register(password: 'x');

    expect(Household::count())->toBe(0)->and(User::count())->toBe(0);
});
