<?php

declare(strict_types=1);

use App\Actions\Gifts\AddIdea;
use App\Enums\GiftStatus;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function ideaData(array $overrides = []): array
{
    return array_merge(['title' => 'Lego set', 'price_cents' => 4999, 'store' => 'Toys R Us', 'url' => 'https://example.com/lego', 'status' => GiftStatus::Idea, 'purchased_by_user_id' => null, 'is_private' => false], $overrides);
}

it('adds an idea for a person with no occasion, as an idea, created by me', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    $gift = app(AddIdea::class)->handle($user, $person->id, ideaData());

    expect($gift->exists)->toBeTrue()
        ->and($gift->household_id)->toBe($user->household_id)
        ->and($gift->recipient_id)->toBe($person->id)
        ->and($gift->occasion_id)->toBeNull()
        ->and($gift->status)->toBe(GiftStatus::Idea)
        ->and($gift->created_by_user_id)->toBe($user->id)
        ->and($gift->title)->toBe('Lego set')
        ->and($gift->price_cents)->toBe(4999)
        ->and($gift->is_private)->toBeFalse();
});

it('ignores status and buyer when adding an idea', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    $gift = app(AddIdea::class)->handle($user, $person->id, ideaData(['status' => GiftStatus::Bought, 'purchased_by_user_id' => $user->id]));

    expect($gift->status)->toBe(GiftStatus::Idea)
        ->and($gift->purchased_by_user_id)->toBeNull();
});

it('adds a private idea when asked', function () {
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();

    expect(app(AddIdea::class)->handle($user, $person->id, ideaData(['is_private' => true]))->is_private)->toBeTrue();
});

it('refuses to add an idea for another household\'s person', function () {
    $user = User::factory()->create();

    app(AddIdea::class)->handle($user, Recipient::factory()->create()->id, ideaData());
})->throws(ModelNotFoundException::class);
