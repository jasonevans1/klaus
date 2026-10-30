<?php

declare(strict_types=1);

use App\Enums\OccasionType;
use App\Models\Household;
use App\Models\Occasion;
use App\Models\Recipient;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('belongs to a household', function () {
    $household = Household::factory()->create();
    $occasion = Occasion::factory()->forHousehold($household)->create();

    expect($occasion->household->is($household))->toBeTrue();
});

it('casts the type to the OccasionType enum and the date to a date', function () {
    $occasion = Occasion::factory()->create()->fresh();

    expect($occasion->type)->toBe(OccasionType::Christmas)
        ->and($occasion->date)->toBeInstanceOf(CarbonInterface::class)
        ->and($occasion->date->toDateString())->toBe('2026-12-25');
});

it('does not allow household_id to be mass assigned', function () {
    $household = Household::factory()->create();

    $occasion = new Occasion(['household_id' => $household->id, 'name' => 'X']);

    expect($occasion->household_id)->toBeNull()
        ->and($occasion->name)->toBe('X');
});

it('stores a budget in cents for each person on the occasion', function () {
    $household = Household::factory()->create();
    $occasion = Occasion::factory()->forHousehold($household)->create();
    $anna = Recipient::factory()->forHousehold($household)->create();
    $ben = Recipient::factory()->forHousehold($household)->create();

    $occasion->recipients()->attach($anna, ['budget_cents' => 15000]);
    $occasion->recipients()->attach($ben, ['budget_cents' => null]);

    expect($occasion->recipients()->find($anna->id)->pivot->budget_cents)->toBe(15000)
        ->and($occasion->recipients()->find($ben->id)->pivot->budget_cents)->toBeNull()
        ->and($anna->occasions()->first()->is($occasion))->toBeTrue()
        ->and($household->occasions()->count())->toBe(1);
});

it('does not allow the same person on an occasion twice', function () {
    $household = Household::factory()->create();
    $occasion = Occasion::factory()->forHousehold($household)->create();
    $anna = Recipient::factory()->forHousehold($household)->create();

    $occasion->recipients()->attach($anna);
    $occasion->recipients()->attach($anna);
})->throws(QueryException::class);

it("removes an occasion's people and budgets when the occasion is deleted", function () {
    $household = Household::factory()->create();
    $occasion = Occasion::factory()->forHousehold($household)->create();
    $anna = Recipient::factory()->forHousehold($household)->create();
    $occasion->recipients()->attach($anna, ['budget_cents' => 5000]);

    $occasion->delete();

    expect(DB::table('occasion_recipient')->count())->toBe(0)
        ->and(Recipient::find($anna->id))->not->toBeNull();
});
