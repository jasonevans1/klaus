<?php

declare(strict_types=1);

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
