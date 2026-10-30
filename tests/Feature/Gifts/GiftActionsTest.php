<?php

declare(strict_types=1);

use App\Actions\Gifts\AddGift;
use App\Actions\Gifts\DeleteGift;
use App\Actions\Gifts\UpdateGift;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function giftData(array $overrides = []): array
{
    return array_merge(['title' => 'Lego set', 'price_cents' => 4999, 'store' => 'Toys R Us', 'url' => 'https://example.com/lego'], $overrides);
}

function giftSetup(): array
{
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $occasion->recipients()->attach($person);

    return [$user, $occasion, $person];
}

it('adds a gift for a person on the occasion and records who added it', function () {
    [$user, $occasion, $person] = giftSetup();

    $gift = app(AddGift::class)->handle($user, $occasion->id, $person->id, giftData());

    expect($gift->exists)->toBeTrue()
        ->and($gift->household_id)->toBe($user->household_id)
        ->and($gift->occasion_id)->toBe($occasion->id)
        ->and($gift->recipient_id)->toBe($person->id)
        ->and($gift->created_by_user_id)->toBe($user->id)
        ->and($gift->title)->toBe('Lego set')
        ->and($gift->price_cents)->toBe(4999)
        ->and($gift->store)->toBe('Toys R Us')
        ->and($gift->url)->toBe('https://example.com/lego');
});

it('refuses to add a gift for someone who is not on the occasion', function () {
    [$user, $occasion] = giftSetup();
    $other = Recipient::factory()->forHousehold($user->household)->create();

    expect(fn () => app(AddGift::class)->handle($user, $occasion->id, $other->id, giftData()))
        ->toThrow(ModelNotFoundException::class);
    expect(Gift::count())->toBe(0);
});

it("refuses to add a gift to another household's occasion", function () {
    [$user] = giftSetup();
    $occasion = Occasion::factory()->create();
    $person = Recipient::factory()->create(['household_id' => $occasion->household_id]);
    $occasion->recipients()->attach($person);

    expect(fn () => app(AddGift::class)->handle($user, $occasion->id, $person->id, giftData()))
        ->toThrow(ModelNotFoundException::class);
});

it("updates a gift's title, price, store and link", function () {
    [$user, $occasion, $person] = giftSetup();
    $gift = Gift::factory()->forPersonOn($person, $occasion)->create();

    app(UpdateGift::class)->handle($user, $gift->id, giftData(['title' => 'Duplo', 'price_cents' => null, 'store' => null, 'url' => null]));

    $gift->refresh();
    expect($gift->title)->toBe('Duplo')
        ->and($gift->price_cents)->toBeNull()
        ->and($gift->store)->toBeNull()
        ->and($gift->url)->toBeNull()
        ->and($gift->recipient_id)->toBe($person->id)
        ->and($gift->occasion_id)->toBe($occasion->id);
});

it('deletes a gift', function () {
    [$user, $occasion, $person] = giftSetup();
    $gift = Gift::factory()->forPersonOn($person, $occasion)->create();

    app(DeleteGift::class)->handle($user, $gift->id);

    expect(Gift::count())->toBe(0);
});

it("refuses to update or delete another household's gift", function () {
    [$user] = giftSetup();
    $gift = Gift::factory()->create(['title' => 'Original']);

    expect(fn () => app(UpdateGift::class)->handle($user, $gift->id, giftData()))->toThrow(ModelNotFoundException::class);
    expect(fn () => app(DeleteGift::class)->handle($user, $gift->id))->toThrow(ModelNotFoundException::class);
    expect($gift->fresh()->title)->toBe('Original');
});
