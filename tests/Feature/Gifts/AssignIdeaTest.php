<?php

declare(strict_types=1);

use App\Actions\Gifts\AssignIdeaToOccasion;
use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function ideaSetup(array $giftState = []): array
{
    $user = User::factory()->create();
    $person = Recipient::factory()->forHousehold($user->household)->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $idea = Gift::factory()->create(array_merge([
        'household_id' => $user->household_id,
        'recipient_id' => $person->id,
        'occasion_id' => null,
    ], $giftState));

    return [$user, $occasion, $person, $idea];
}

it('moves a year-round idea onto an occasion the person is on', function () {
    [$user, $occasion, $person, $idea] = ideaSetup();
    $occasion->recipients()->attach($person);

    $gift = app(AssignIdeaToOccasion::class)->handle($user, $idea->id, $occasion->id);

    expect($gift->occasion_id)->toBe($occasion->id)
        ->and($idea->fresh()->occasion_id)->toBe($occasion->id);
});

it("keeps the idea's details and private flag when moved", function () {
    [$user, $occasion, $person, $idea] = ideaSetup([
        'title' => 'Kettle', 'price_cents' => 2500, 'store' => 'Shop', 'url' => 'https://example.com/k',
        'is_private' => true,
    ]);
    $idea->forceFill(['created_by_user_id' => $user->id])->save();
    $occasion->recipients()->attach($person);

    app(AssignIdeaToOccasion::class)->handle($user, $idea->id, $occasion->id);

    $idea->refresh();
    expect($idea->title)->toBe('Kettle')
        ->and($idea->price_cents)->toBe(2500)
        ->and($idea->store)->toBe('Shop')
        ->and($idea->url)->toBe('https://example.com/k')
        ->and($idea->is_private)->toBeTrue()
        ->and($idea->created_by_user_id)->toBe($user->id)
        ->and($idea->status)->toBe(GiftStatus::Idea);
});

it('refuses to move an idea onto an occasion the person is not on', function () {
    [$user, $occasion, , $idea] = ideaSetup();

    expect(fn () => app(AssignIdeaToOccasion::class)->handle($user, $idea->id, $occasion->id))
        ->toThrow(ModelNotFoundException::class);
    expect($idea->fresh()->occasion_id)->toBeNull();
});

it('leaves a gift that is already on an occasion where it is', function () {
    [$user, $occasion, $person, $idea] = ideaSetup();
    $first = Occasion::factory()->forHousehold($user->household)->create();
    $first->recipients()->attach($person);
    $occasion->recipients()->attach($person);
    $idea->forceFill(['occasion_id' => $first->id])->save();

    $gift = app(AssignIdeaToOccasion::class)->handle($user, $idea->id, $occasion->id);

    expect($gift->occasion_id)->toBe($first->id)
        ->and($idea->fresh()->occasion_id)->toBe($first->id);
});

it("refuses to move my partner's private idea", function () {
    [$user, $occasion, $person, $idea] = ideaSetup();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $idea->forceFill(['is_private' => true, 'created_by_user_id' => $partner->id])->save();
    $occasion->recipients()->attach($person);

    expect(fn () => app(AssignIdeaToOccasion::class)->handle($user, $idea->id, $occasion->id))
        ->toThrow(ModelNotFoundException::class);
    expect($idea->fresh()->occasion_id)->toBeNull();
});

it("refuses to move an idea onto another household's occasion", function () {
    [$user, , $person, $idea] = ideaSetup();
    $foreign = Occasion::factory()->create();
    $foreign->recipients()->attach($person);

    expect(fn () => app(AssignIdeaToOccasion::class)->handle($user, $idea->id, $foreign->id))
        ->toThrow(ModelNotFoundException::class);
    expect($idea->fresh()->occasion_id)->toBeNull();
});
