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
