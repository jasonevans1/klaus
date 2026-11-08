<?php

declare(strict_types=1);

use App\Actions\Occasions\CreateBirthdays;
use App\Actions\Occasions\CreateChristmas;
use App\Actions\Occasions\DeleteOccasion;
use App\Enums\OccasionType;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use App\Repositories\OccasionRepository;
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

it('creates a Birthdays occasion for the year dated January 1', function () {
    $user = User::factory()->create();

    $occasion = app(CreateBirthdays::class)->handle($user, 2027);

    expect($occasion->household_id)->toBe($user->household_id)
        ->and($occasion->type)->toBe(OccasionType::Birthdays)
        ->and($occasion->name)->toBe('Birthdays 2027')
        ->and($occasion->date->toDateString())->toBe('2027-01-01')
        ->and($occasion->birthday_recipient_id)->toBeNull();
});

it('adds every person with a birthday to the Birthdays occasion', function () {
    $user = User::factory()->create();
    $amy = Recipient::factory()->forHousehold($user->household)->withBirthday(3, 4)->create();
    $bob = Recipient::factory()->forHousehold($user->household)->withBirthday(12, 1)->create();

    $occasion = app(CreateBirthdays::class)->handle($user, 2027);

    expect($occasion->recipients()->pluck('recipients.id')->sort()->values()->all())->toBe([$amy->id, $bob->id]);
});

it('leaves out people without a birthday and archived people', function () {
    $user = User::factory()->create();
    Recipient::factory()->forHousehold($user->household)->create();
    Recipient::factory()->forHousehold($user->household)->withBirthday(3, 4)->archived()->create();

    expect(app(CreateBirthdays::class)->handle($user, 2027)->recipients()->count())->toBe(0);
});

it('copies each person\'s budget from the previous year\'s Birthdays occasion', function () {
    $user = User::factory()->create();
    $amy = Recipient::factory()->forHousehold($user->household)->withBirthday(3, 4)->create();
    Occasion::factory()->forHousehold($user->household)->birthdays(2025)->create()
        ->recipients()->attach($amy, ['budget_cents' => 4000]);

    $occasion = app(CreateBirthdays::class)->handle($user, 2026);

    expect($occasion->recipients()->pluck('occasion_recipient.budget_cents', 'recipients.id')->all())
        ->toBe([$amy->id => 4000]);
});

it('gives people new since last year no budget', function () {
    $user = User::factory()->create();
    $amy = Recipient::factory()->forHousehold($user->household)->withBirthday(3, 4)->create();
    $new = Recipient::factory()->forHousehold($user->household)->withBirthday(5, 6)->create();
    Occasion::factory()->forHousehold($user->household)->birthdays(2025)->create()
        ->recipients()->attach($amy, ['budget_cents' => 4000]);

    $occasion = app(CreateBirthdays::class)->handle($user, 2026);

    expect($occasion->recipients()->pluck('occasion_recipient.budget_cents', 'recipients.id')->all())
        ->toBe([$amy->id => 4000, $new->id => null]);
});

it('refuses a second Birthdays occasion for the same year', function () {
    $user = User::factory()->create();
    Occasion::factory()->forHousehold($user->household)->birthdays(2026)->create();

    expect(fn () => app(CreateBirthdays::class)->handle($user, 2026))
        ->toThrow(ValidationException::class, 'You already have Birthdays 2026.');
    expect(Occasion::count())->toBe(1);
});

it('ignores old per-person birthday occasions when checking for duplicates and copying budgets', function () {
    $user = User::factory()->create();
    $amy = Recipient::factory()->forHousehold($user->household)->withBirthday(3, 4)->create();
    Occasion::factory()->birthday($amy, '2026-03-04')->create()
        ->recipients()->attach($amy, ['budget_cents' => 9000]);

    $occasion = app(CreateBirthdays::class)->handle($user, 2026);

    expect($occasion->recipients()->pluck('occasion_recipient.budget_cents', 'recipients.id')->all())
        ->toBe([$amy->id => null]);
});

it('is not blocked by a per-person birthday occasion whose person was deleted', function () {
    $user = User::factory()->create();
    $gone = Recipient::factory()->forHousehold($user->household)->withBirthday(3, 4)->create();
    Occasion::factory()->birthday($gone, '2026-03-04')->create();
    $gone->delete();

    expect(app(CreateBirthdays::class)->handle($user, 2026)->exists)->toBeTrue();
});

it('finds that year\'s Birthdays occasion for the household', function () {
    $user = User::factory()->create();
    $mine = Occasion::factory()->forHousehold($user->household)->birthdays(2026)->create();
    Occasion::factory()->forHousehold($user->household)->birthdays(2025)->create();
    Occasion::factory()->birthdays(2026)->create();

    $repo = app(OccasionRepository::class);

    expect($repo->birthdaysFor($user, 2026)?->id)->toBe($mine->id)
        ->and($repo->birthdaysFor($user, 2027))->toBeNull();
});

it('only adds people from the user\'s own household', function () {
    $user = User::factory()->create();
    Recipient::factory()->withBirthday(3, 4)->create();

    expect(app(CreateBirthdays::class)->handle($user, 2026)->recipients()->count())->toBe(0);
});
