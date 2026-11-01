<?php

declare(strict_types=1);

use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;

it('stores a gift with its title, price, store and link for a person on an occasion', function () {
    $gift = Gift::factory()->create([
        'title' => 'Lego set',
        'price_cents' => 4999,
        'store' => 'Target',
        'url' => 'https://example.com/lego',
    ])->fresh();

    expect($gift->title)->toBe('Lego set')
        ->and($gift->price_cents)->toBe(4999)
        ->and($gift->store)->toBe('Target')
        ->and($gift->url)->toBe('https://example.com/lego')
        ->and($gift->recipient)->toBeInstanceOf(Recipient::class)
        ->and($gift->occasion)->toBeInstanceOf(Occasion::class)
        ->and($gift->household->id)->toBe($gift->recipient->household_id);
});

it('allows a gift with no price, store or link', function () {
    $gift = Gift::factory()->create(['price_cents' => null, 'store' => null, 'url' => null])->fresh();

    expect($gift->price_cents)->toBeNull()
        ->and($gift->store)->toBeNull()
        ->and($gift->url)->toBeNull();
});

it("lists a person's gifts through the person and an occasion's gifts through the occasion", function () {
    $gift = Gift::factory()->create();
    Gift::factory()->create();

    expect($gift->recipient->gifts->modelKeys())->toBe([$gift->id])
        ->and($gift->occasion->gifts->modelKeys())->toBe([$gift->id]);
});

it("deletes an occasion's gifts when the occasion is deleted", function () {
    $gift = Gift::factory()->create();

    $gift->occasion->delete();

    expect(Gift::count())->toBe(0);
});

it('keeps the gift when the user who added it is deleted', function () {
    $user = User::factory()->create();
    $gift = Gift::factory()->create(['created_by_user_id' => $user->id]);

    $user->delete();

    expect($gift->fresh()->created_by_user_id)->toBeNull();
});

it("makes factory gifts whose person and occasion belong to the gift's household", function () {
    $gift = Gift::factory()->create();

    expect($gift->recipient->household_id)->toBe($gift->household_id)
        ->and($gift->occasion->household_id)->toBe($gift->household_id)
        ->and($gift->occasion->recipients->modelKeys())->toBe([$gift->recipient_id]);
});

it('makes several factory gifts for a person who is already on the occasion', function () {
    $recipient = Recipient::factory()->create();
    $occasion = Occasion::factory()->forHousehold($recipient->household)->create();
    $occasion->recipients()->attach($recipient);

    Gift::factory()->count(3)->forPersonOn($recipient, $occasion)->create();

    expect($occasion->gifts()->count())->toBe(3)
        ->and($occasion->recipients()->count())->toBe(1);
});

it('starts a new gift at Idea with no buyer', function () {
    expect((new Gift)->status)->toBe(GiftStatus::Idea);

    $gift = Gift::factory()->create()->fresh();

    expect($gift->status)->toBe(GiftStatus::Idea)
        ->and($gift->purchased_by_user_id)->toBeNull()
        ->and($gift->purchasedBy)->toBeNull();
});

it("reads a gift's status as a GiftStatus", function () {
    $buyer = User::factory()->create();

    expect(Gift::factory()->bought($buyer)->create()->fresh()->status)->toBe(GiftStatus::Bought)
        ->and(Gift::factory()->wrapped($buyer)->create()->fresh()->status)->toBe(GiftStatus::Wrapped)
        ->and(Gift::factory()->given($buyer)->create()->fresh()->status)->toBe(GiftStatus::Given);
});

it('knows which household member bought a gift', function () {
    $buyer = User::factory()->create();
    $gift = Gift::factory()->bought($buyer)->create()->fresh();

    expect($gift->purchased_by_user_id)->toBe($buyer->id)
        ->and($gift->purchasedBy->is($buyer))->toBeTrue();
});

it("keeps the gift but forgets the buyer when the buyer's account is deleted", function () {
    $buyer = User::factory()->create();
    $gift = Gift::factory()->bought($buyer)->create();

    $buyer->delete();
    $gift = $gift->fresh();

    expect($gift)->not->toBeNull()
        ->and($gift->purchased_by_user_id)->toBeNull();
});

it('makes a new gift not private', function () {
    expect((new Gift)->is_private)->toBeFalse()
        ->and(Gift::factory()->create()->fresh()->is_private)->toBeFalse();
});

it("reads a gift's private flag as a boolean", function () {
    $owner = User::factory()->create();
    $gift = Gift::factory()->private($owner)->create()->fresh();

    expect($gift->is_private)->toBeTrue()
        ->and($gift->created_by_user_id)->toBe($owner->id);
});

it('shows a private gift to the person who added it', function () {
    $me = User::factory()->create();
    $gift = Gift::factory()->private($me)->create(['household_id' => $me->household_id]);

    expect(Gift::visibleTo($me)->pluck('id')->all())->toBe([$gift->id]);
});

it('hides a private gift from the other household member', function () {
    $me = User::factory()->create();
    $partner = User::factory()->forHousehold($me->household)->create();
    Gift::factory()->private($me)->create(['household_id' => $me->household_id]);

    expect(Gift::visibleTo($partner)->count())->toBe(0);
});

it('shows gifts that are not private to both household members', function () {
    $me = User::factory()->create();
    $partner = User::factory()->forHousehold($me->household)->create();
    $gift = Gift::factory()->create(['household_id' => $me->household_id, 'created_by_user_id' => $me->id]);

    expect(Gift::visibleTo($me)->pluck('id')->all())->toBe([$gift->id])
        ->and(Gift::visibleTo($partner)->pluck('id')->all())->toBe([$gift->id]);
});

it('hides every gift from another household', function () {
    $me = User::factory()->create();
    $stranger = User::factory()->create();
    Gift::factory()->create(['household_id' => $me->household_id]);
    Gift::factory()->private($me)->create(['household_id' => $me->household_id]);

    expect(Gift::visibleTo($stranger)->count())->toBe(0);
});
