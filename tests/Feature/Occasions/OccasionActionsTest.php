<?php

declare(strict_types=1);

use App\Actions\Occasions\CreateChristmas;
use App\Actions\Occasions\DeleteOccasion;
use App\Enums\OccasionType;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
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

it('copies last Christmas\'s people and budgets onto the new Christmas', function () {
    $user = User::factory()->create();
    $last = Occasion::factory()->forHousehold($user->household)->christmas(2025)->create();
    $amy = Recipient::factory()->forHousehold($user->household)->create();
    $bob = Recipient::factory()->forHousehold($user->household)->create();
    $last->recipients()->attach($amy, ['budget_cents' => 5000]);
    $last->recipients()->attach($bob, ['budget_cents' => 2500]);

    $occasion = app(CreateChristmas::class)->handle($user, 2026);

    expect($occasion->recipients()->pluck('occasion_recipient.budget_cents', 'recipients.id')->all())
        ->toBe([$amy->id => 5000, $bob->id => 2500]);
});

it('copies from the most recent earlier Christmas when there are several', function () {
    $user = User::factory()->create();
    $old = Recipient::factory()->forHousehold($user->household)->create();
    $recent = Recipient::factory()->forHousehold($user->household)->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2024)->create()->recipients()->attach($old, ['budget_cents' => 1000]);
    Occasion::factory()->forHousehold($user->household)->christmas(2025)->create()->recipients()->attach($recent, ['budget_cents' => 2000]);

    $occasion = app(CreateChristmas::class)->handle($user, 2026);

    expect($occasion->recipients()->pluck('recipients.id')->all())->toBe([$recent->id]);
});

it('leaves out archived people when copying', function () {
    $user = User::factory()->create();
    $last = Occasion::factory()->forHousehold($user->household)->christmas(2025)->create();
    $active = Recipient::factory()->forHousehold($user->household)->create();
    $archived = Recipient::factory()->forHousehold($user->household)->archived()->create();
    $last->recipients()->attach($active, ['budget_cents' => 1000]);
    $last->recipients()->attach($archived, ['budget_cents' => 1000]);

    $occasion = app(CreateChristmas::class)->handle($user, 2026);

    expect($occasion->recipients()->pluck('recipients.id')->all())->toBe([$active->id]);
});

it('starts empty when there is no earlier Christmas', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->christmas(2027)->create()
        ->recipients()->attach(Recipient::factory()->forHousehold($user->household)->create(), ['budget_cents' => 1000]);

    expect(app(CreateChristmas::class)->handle($user, 2026)->recipients()->count())->toBe(0);
});

it('does not copy gifts', function () {
    $user = User::factory()->create();
    $last = Occasion::factory()->forHousehold($user->household)->christmas(2025)->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $last->recipients()->attach($person, ['budget_cents' => 1000]);
    Gift::factory()->create(['occasion_id' => $last->id, 'recipient_id' => $person->id]);

    $occasion = app(CreateChristmas::class)->handle($user, 2026);

    expect($occasion->gifts()->count())->toBe(0)
        ->and(Gift::count())->toBe(1);
});

it('does not copy from birthdays or another household\'s Christmas', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    Occasion::factory()->birthday($person, '2026-06-01')->create()
        ->recipients()->attach($person, ['budget_cents' => 1000]);
    $other = Occasion::factory()->christmas(2025)->create();
    $other->recipients()->attach(Recipient::factory()->forHousehold($other->household)->create(), ['budget_cents' => 1000]);

    expect(app(CreateChristmas::class)->handle($user, 2026)->recipients()->count())->toBe(0);
});
