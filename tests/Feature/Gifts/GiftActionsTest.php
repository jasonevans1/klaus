<?php

declare(strict_types=1);

use App\Actions\Gifts\AddGift;
use App\Actions\Gifts\AdvanceGiftStatus;
use App\Actions\Gifts\DeleteGift;
use App\Actions\Gifts\UpdateGift;
use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function giftData(array $overrides = []): array
{
    return array_merge(['title' => 'Lego set', 'price_cents' => 4999, 'store' => 'Toys R Us', 'url' => 'https://example.com/lego', 'status' => GiftStatus::Idea, 'purchased_by_user_id' => null], $overrides);
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

it('marks an idea as bought by whoever clicked', function () {
    [$user, $occasion, $person] = giftSetup();
    $gift = Gift::factory()->forPersonOn($person, $occasion)->create();

    app(AdvanceGiftStatus::class)->handle($user, $gift->id, GiftStatus::Idea);

    $gift->refresh();
    expect($gift->status)->toBe(GiftStatus::Bought)
        ->and($gift->purchased_by_user_id)->toBe($user->id);
});

it('moves a bought gift to wrapped and keeps the buyer', function () {
    [$user, $occasion, $person] = giftSetup();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $gift = Gift::factory()->forPersonOn($person, $occasion)->bought($partner)->create();

    app(AdvanceGiftStatus::class)->handle($user, $gift->id, GiftStatus::Bought);

    $gift->refresh();
    expect($gift->status)->toBe(GiftStatus::Wrapped)
        ->and($gift->purchased_by_user_id)->toBe($partner->id);
});

it('moves a wrapped gift to given', function () {
    [$user, $occasion, $person] = giftSetup();
    $gift = Gift::factory()->forPersonOn($person, $occasion)->wrapped($user)->create();

    app(AdvanceGiftStatus::class)->handle($user, $gift->id, GiftStatus::Wrapped);

    expect($gift->refresh()->status)->toBe(GiftStatus::Given);
});

it('leaves a given gift as given', function () {
    [$user, $occasion, $person] = giftSetup();
    $gift = Gift::factory()->forPersonOn($person, $occasion)->given($user)->create();

    app(AdvanceGiftStatus::class)->handle($user, $gift->id, GiftStatus::Given);

    expect($gift->refresh()->status)->toBe(GiftStatus::Given);
});

it("refuses to advance another household's gift", function () {
    [$user] = giftSetup();
    $gift = Gift::factory()->create();

    expect(fn () => app(AdvanceGiftStatus::class)->handle($user, $gift->id, GiftStatus::Idea))
        ->toThrow(ModelNotFoundException::class);
    expect($gift->fresh()->status)->toBe(GiftStatus::Idea);
});

it('leaves a gift alone when it has already moved on from the status that was clicked', function () {
    [$user, $occasion, $person] = giftSetup();
    $gift = Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create();

    app(AdvanceGiftStatus::class)->handle($user, $gift->id, GiftStatus::Idea);

    expect($gift->refresh()->status)->toBe(GiftStatus::Bought);
});

it('saves the status and buyer picked when adding or editing a gift', function () {
    [$user, $occasion, $person] = giftSetup();
    $partner = User::factory()->create(['household_id' => $user->household_id]);

    $gift = app(AddGift::class)->handle($user, $occasion->id, $person->id, giftData(['status' => GiftStatus::Bought, 'purchased_by_user_id' => $partner->id]));
    expect($gift->fresh()->status)->toBe(GiftStatus::Bought)
        ->and($gift->fresh()->purchased_by_user_id)->toBe($partner->id);

    app(UpdateGift::class)->handle($user, $gift->id, giftData(['status' => GiftStatus::Wrapped, 'purchased_by_user_id' => $user->id]));
    expect($gift->fresh()->status)->toBe(GiftStatus::Wrapped)
        ->and($gift->fresh()->purchased_by_user_id)->toBe($user->id);
});

it('clears the buyer when a gift is set back to Idea', function () {
    [$user, $occasion, $person] = giftSetup();
    $gift = Gift::factory()->forPersonOn($person, $occasion)->bought($user)->create();

    app(UpdateGift::class)->handle($user, $gift->id, giftData(['status' => GiftStatus::Idea, 'purchased_by_user_id' => $user->id]));

    expect($gift->fresh()->status)->toBe(GiftStatus::Idea)
        ->and($gift->fresh()->purchased_by_user_id)->toBeNull();
});

it('makes the person saving the buyer when a bought gift has no buyer picked', function () {
    [$user, $occasion, $person] = giftSetup();

    $gift = app(AddGift::class)->handle($user, $occasion->id, $person->id, giftData(['status' => GiftStatus::Bought]));

    expect($gift->fresh()->purchased_by_user_id)->toBe($user->id);
});

it('refuses a buyer from another household', function () {
    [$user, $occasion, $person] = giftSetup();
    $stranger = User::factory()->create();

    expect(fn () => app(AddGift::class)->handle($user, $occasion->id, $person->id, giftData(['status' => GiftStatus::Bought, 'purchased_by_user_id' => $stranger->id])))
        ->toThrow(ModelNotFoundException::class);
    expect(Gift::count())->toBe(0);
});
