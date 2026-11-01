<?php

declare(strict_types=1);

use App\Actions\Gifts\AdvanceGiftStatus;
use App\Actions\Gifts\DeleteGift;
use App\Actions\Gifts\SetGiftWrapped;
use App\Actions\Gifts\UpdateGift;
use App\Enums\GiftStatus;
use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use App\Models\User;
use App\Repositories\GiftRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function gifts(): GiftRepository
{
    return app(GiftRepository::class);
}

it('finds a gift in my household', function () {
    $user = User::factory()->create();
    $gift = Gift::factory()->create(['household_id' => $user->household_id]);

    expect(gifts()->findForUser($user, $gift->id)->is($gift))->toBeTrue();
});

it('does not find another household\'s gift', function () {
    $user = User::factory()->create();
    $other = Gift::factory()->create();

    gifts()->findForUser($user, $other->id);
})->throws(ModelNotFoundException::class);

it('lists an occasion\'s gifts by person in the order they were added', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $bob = Recipient::factory()->create(['household_id' => $user->household_id]);
    $first = Gift::factory()->forPersonOn($ann, $occasion)->create();
    $other = Gift::factory()->forPersonOn($bob, $occasion)->create();
    $second = Gift::factory()->forPersonOn($ann, $occasion)->create();

    $byPerson = gifts()->byPerson($user, $occasion);

    expect($byPerson[$ann->id]->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($byPerson[$bob->id]->pluck('id')->all())->toBe([$other->id]);
});

it('leaves out the same person\'s gifts on other occasions', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $elsewhere = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $here = Gift::factory()->forPersonOn($ann, $occasion)->create();
    Gift::factory()->forPersonOn($ann, $elsewhere)->create();

    expect(gifts()->byPerson($user, $occasion)[$ann->id]->pluck('id')->all())->toBe([$here->id]);
});

it('lists no gifts for another household\'s occasion', function () {
    $user = User::factory()->create();
    Gift::factory()->create();
    $theirs = Gift::query()->firstOrFail()->occasion;

    expect(gifts()->byPerson($user, $theirs))->toBe([]);
});

it('loads who bought each gift along with the occasion\'s gifts', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    Gift::factory()->forPersonOn($ann, $occasion)->bought($user)->create();

    $gift = gifts()->byPerson($user, $occasion)[$ann->id]->first();

    expect($gift->relationLoaded('purchasedBy'))->toBeTrue()
        ->and($gift->purchasedBy->is($user))->toBeTrue();
});

it('lists both household members as possible buyers', function () {
    $me = User::factory()->create(['name' => 'Zed']);
    $partner = User::factory()->create(['name' => 'Amy', 'household_id' => $me->household_id]);

    expect(gifts()->buyers($me)->pluck('id')->all())->toBe([$partner->id, $me->id]);
});

it('does not offer another household\'s members as buyers', function () {
    $me = User::factory()->create();
    User::factory()->create();

    expect(gifts()->buyers($me)->pluck('id')->all())->toBe([$me->id]);
});

it('lists only the gifts still at idea on the occasion', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $idea = Gift::factory()->forPersonOn($ann, $occasion)->create();
    Gift::factory()->forPersonOn($ann, $occasion)->bought($user)->create();
    Gift::factory()->forPersonOn($ann, $occasion)->wrapped($user)->create();
    Gift::factory()->forPersonOn($ann, $occasion)->given($user)->create();

    expect(gifts()->ideas($user, $occasion)->pluck('id')->all())->toBe([$idea->id]);
});

it('lists ideas for everyone on the occasion', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $bob = Recipient::factory()->create(['household_id' => $user->household_id]);
    $forAnn = Gift::factory()->forPersonOn($ann, $occasion)->create();
    $forBob = Gift::factory()->forPersonOn($bob, $occasion)->create();

    expect(gifts()->ideas($user, $occasion)->pluck('id')->all())->toEqualCanonicalizing([$forAnn->id, $forBob->id]);
});

it('leaves out ideas from other occasions', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $elsewhere = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $here = Gift::factory()->forPersonOn($ann, $occasion)->create();
    Gift::factory()->forPersonOn($ann, $elsewhere)->create();

    expect(gifts()->ideas($user, $occasion)->pluck('id')->all())->toBe([$here->id]);
});

it('lists no ideas for another household\'s occasion', function () {
    $user = User::factory()->create();
    Gift::factory()->create();
    $theirs = Gift::query()->firstOrFail()->occasion;

    expect(gifts()->ideas($user, $theirs))->toBeEmpty();
});

it('orders ideas by person name and then the order they were added', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $zed = Recipient::factory()->create(['household_id' => $user->household_id, 'name' => 'zed']);
    $ann = Recipient::factory()->create(['household_id' => $user->household_id, 'name' => 'Ann']);
    $first = Gift::factory()->forPersonOn($zed, $occasion)->create();
    $second = Gift::factory()->forPersonOn($ann, $occasion)->create();
    $third = Gift::factory()->forPersonOn($zed, $occasion)->create();

    $ideas = gifts()->ideas($user, $occasion);

    expect($ideas->pluck('id')->all())->toBe([$second->id, $first->id, $third->id])
        ->and($ideas->first()->relationLoaded('recipient'))->toBeTrue();
});

it('lists the gifts that are bought or wrapped on the occasion', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $bought = Gift::factory()->forPersonOn($ann, $occasion)->bought($user)->create();
    $wrapped = Gift::factory()->forPersonOn($ann, $occasion)->wrapped($user)->create();

    expect(gifts()->toWrap($user, $occasion)->pluck('id')->all())->toEqualCanonicalizing([$bought->id, $wrapped->id]);
});

it('leaves out ideas and given gifts from the wrapping list', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $bought = Gift::factory()->forPersonOn($ann, $occasion)->bought($user)->create();
    Gift::factory()->forPersonOn($ann, $occasion)->create();
    Gift::factory()->forPersonOn($ann, $occasion)->given($user)->create();

    expect(gifts()->toWrap($user, $occasion)->pluck('id')->all())->toBe([$bought->id]);
});

it('leaves out gifts to wrap from other occasions', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $elsewhere = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $here = Gift::factory()->forPersonOn($ann, $occasion)->bought($user)->create();
    Gift::factory()->forPersonOn($ann, $elsewhere)->bought($user)->create();

    expect(gifts()->toWrap($user, $occasion)->pluck('id')->all())->toBe([$here->id]);
});

it('lists nothing to wrap for another household\'s occasion', function () {
    $user = User::factory()->create();
    Gift::factory()->bought(User::factory()->create())->create();
    $theirs = Gift::query()->firstOrFail()->occasion;

    expect(gifts()->toWrap($user, $theirs))->toBeEmpty();
});

it('orders gifts to wrap by person name and then the order they were added', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $zed = Recipient::factory()->create(['household_id' => $user->household_id, 'name' => 'zed']);
    $ann = Recipient::factory()->create(['household_id' => $user->household_id, 'name' => 'Ann']);
    $first = Gift::factory()->forPersonOn($zed, $occasion)->bought($user)->create();
    $second = Gift::factory()->forPersonOn($ann, $occasion)->wrapped($user)->create();
    $third = Gift::factory()->forPersonOn($zed, $occasion)->bought($user)->create();

    $gifts = gifts()->toWrap($user, $occasion);

    expect($gifts->pluck('id')->all())->toBe([$second->id, $first->id, $third->id])
        ->and($gifts->first()->relationLoaded('recipient'))->toBeTrue();
});

function privateGiftSetup(): array
{
    $me = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $me->household_id]);
    $occasion = Occasion::factory()->forHousehold($me->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $me->household_id]);

    return [$me, $partner, $occasion, $ann];
}

it('does not find my partner\'s private gift by id', function () {
    [$me, $partner, $occasion, $ann] = privateGiftSetup();
    $secret = Gift::factory()->forPersonOn($ann, $occasion)->private($me)->create();

    gifts()->findForUser($partner, $secret->id);
})->throws(ModelNotFoundException::class);

it('finds my own private gift by id', function () {
    [$me, , $occasion, $ann] = privateGiftSetup();
    $secret = Gift::factory()->forPersonOn($ann, $occasion)->private($me)->create();

    expect(gifts()->findForUser($me, $secret->id)->is($secret))->toBeTrue();
});

it('leaves my partner\'s private gifts out of the gifts on an occasion', function () {
    [$me, $partner, $occasion, $ann] = privateGiftSetup();
    $shared = Gift::factory()->forPersonOn($ann, $occasion)->create();
    $secret = Gift::factory()->forPersonOn($ann, $occasion)->private($me)->create();

    expect(gifts()->byPerson($partner, $occasion)[$ann->id]->pluck('id')->all())->toBe([$shared->id])
        ->and(gifts()->byPerson($me, $occasion)[$ann->id]->pluck('id')->all())->toBe([$shared->id, $secret->id]);
});

it('leaves my partner\'s private gifts out of the shopping list ideas', function () {
    [$me, $partner, $occasion, $ann] = privateGiftSetup();
    $shared = Gift::factory()->forPersonOn($ann, $occasion)->create();
    Gift::factory()->forPersonOn($ann, $occasion)->private($me)->create();

    expect(gifts()->ideas($partner, $occasion)->pluck('id')->all())->toBe([$shared->id]);
});

it('leaves my partner\'s private gifts out of the gifts to wrap', function () {
    [$me, $partner, $occasion, $ann] = privateGiftSetup();
    $shared = Gift::factory()->forPersonOn($ann, $occasion)->bought($me)->create();
    Gift::factory()->forPersonOn($ann, $occasion)->bought($me)->private($me)->create();

    expect(gifts()->toWrap($partner, $occasion)->pluck('id')->all())->toBe([$shared->id]);
});

it('does not let my partner change, advance, wrap or delete my private gift', function () {
    [$me, $partner, $occasion, $ann] = privateGiftSetup();
    $secret = Gift::factory()->forPersonOn($ann, $occasion)->bought($me)->private($me)->create();
    $data = ['title' => 'x', 'price_cents' => null, 'store' => null, 'url' => null, 'status' => GiftStatus::Bought, 'purchased_by_user_id' => $me->id, 'is_private' => false];

    $attempts = [
        fn () => app(UpdateGift::class)->handle($partner, $secret->id, $data),
        fn () => app(AdvanceGiftStatus::class)->handle($partner, $secret->id, GiftStatus::Bought),
        fn () => app(SetGiftWrapped::class)->handle($partner, $secret->id, true),
        fn () => app(DeleteGift::class)->handle($partner, $secret->id),
    ];

    foreach ($attempts as $attempt) {
        expect($attempt)->toThrow(ModelNotFoundException::class);
    }
    expect($secret->fresh())->not->toBeNull();
});

it('lists a household\'s year-round ideas by person in the order they were added', function () {
    $user = User::factory()->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $bob = Recipient::factory()->create(['household_id' => $user->household_id]);
    $first = Gift::factory()->yearRound()->create(['household_id' => $user->household_id, 'recipient_id' => $ann->id]);
    $other = Gift::factory()->yearRound()->create(['household_id' => $user->household_id, 'recipient_id' => $bob->id]);
    $second = Gift::factory()->yearRound()->create(['household_id' => $user->household_id, 'recipient_id' => $ann->id]);

    $byPerson = gifts()->yearRoundIdeas($user);

    expect($byPerson[$ann->id]->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($byPerson[$bob->id]->pluck('id')->all())->toBe([$other->id]);
});

it('leaves gifts that are on an occasion out of the year-round ideas', function () {
    $user = User::factory()->create();
    $occasion = Occasion::factory()->forHousehold($user->household)->create();
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    Gift::factory()->forPersonOn($ann, $occasion)->create();
    $idea = Gift::factory()->yearRound()->create(['household_id' => $user->household_id, 'recipient_id' => $ann->id]);

    expect(gifts()->yearRoundIdeas($user)[$ann->id]->pluck('id')->all())->toBe([$idea->id]);
});

it('leaves my partner\'s private ideas out of my year-round ideas but includes mine', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create(['household_id' => $user->household_id]);
    $ann = Recipient::factory()->create(['household_id' => $user->household_id]);
    $attrs = ['household_id' => $user->household_id, 'recipient_id' => $ann->id];
    $shared = Gift::factory()->yearRound()->create($attrs);
    $mine = Gift::factory()->yearRound()->private($user)->create($attrs);
    Gift::factory()->yearRound()->private($partner)->create($attrs);

    expect(gifts()->yearRoundIdeas($user)[$ann->id]->pluck('id')->all())->toBe([$shared->id, $mine->id]);
});
