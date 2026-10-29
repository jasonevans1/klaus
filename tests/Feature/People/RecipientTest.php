<?php

declare(strict_types=1);

use App\Enums\RecipientGroup;
use App\Models\Household;
use App\Models\Recipient;

it('belongs to a household', function () {
    $household = Household::factory()->create();
    $recipient = Recipient::factory()->forHousehold($household)->create();

    expect($recipient->household->is($household))->toBeTrue();
});

it('casts the group to the RecipientGroup enum', function () {
    $recipient = Recipient::factory()->create(['group' => 'teachers']);

    expect($recipient->fresh()->group)->toBe(RecipientGroup::Teachers);
});

it('does not allow household_id to be mass assigned', function () {
    $household = Household::factory()->create();
    $recipient = new Recipient(['household_id' => $household->id, 'name' => 'Ann']);

    expect($recipient->household_id)->toBeNull()
        ->and($recipient->name)->toBe('Ann');
});

it('lists a household\'s people through the recipients relation', function () {
    $household = Household::factory()->create();
    $mine = Recipient::factory()->forHousehold($household)->create();
    Recipient::factory()->create();

    expect($household->recipients->pluck('id')->all())->toBe([$mine->id]);
});

it('deletes a household\'s people when the household is deleted', function () {
    $household = Household::factory()->create();
    Recipient::factory()->forHousehold($household)->create();

    $household->delete();

    expect(Recipient::count())->toBe(0);
});
