<?php

declare(strict_types=1);

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
