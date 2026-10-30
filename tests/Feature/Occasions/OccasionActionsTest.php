<?php

declare(strict_types=1);

use App\Actions\Occasions\CreateChristmas;
use App\Actions\Occasions\DeleteOccasion;
use App\Enums\OccasionType;
use App\Models\Occasion;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

it('creates an empty christmas for the year in my household', function () {
    $user = User::factory()->create();

    $occasion = app(CreateChristmas::class)->handle($user, 2026);

    expect($occasion->household_id)->toBe($user->household_id)
        ->and($occasion->type)->toBe(OccasionType::Christmas)
        ->and($occasion->recipients()->count())->toBe(0);
});

it('names christmas after its year and dates it december 25', function () {
    $occasion = app(CreateChristmas::class)->handle(User::factory()->create(), 2027);

    expect($occasion->name)->toBe('Christmas 2027')
        ->and($occasion->date->toDateString())->toBe('2027-12-25');
});

it('refuses a second christmas for the same year', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2026)->create();

    expect(fn () => app(CreateChristmas::class)->handle($user, 2026))
        ->toThrow(ValidationException::class, 'You already have Christmas 2026.');
    expect(Occasion::count())->toBe(1);
});

it('allows christmas for the same year in another household', function () {
    Occasion::factory()->christmas(2026)->create();

    $occasion = app(CreateChristmas::class)->handle(User::factory()->create(), 2026);

    expect($occasion->exists)->toBeTrue()
        ->and(Occasion::count())->toBe(2);
});

it('deletes one of my occasions', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();

    app(DeleteOccasion::class)->handle($user, $occasion->id);

    expect(Occasion::count())->toBe(0);
});

it("cannot delete another household's occasion", function () {
    $occasion = Occasion::factory()->create();

    expect(fn () => app(DeleteOccasion::class)->handle(User::factory()->create(), $occasion->id))
        ->toThrow(ModelNotFoundException::class);
    expect(Occasion::count())->toBe(1);
});
