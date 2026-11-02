<?php

declare(strict_types=1);

use App\Models\Occasion;
use App\Models\Recipient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01'));
});

it('creates upcoming birthdays when the command runs', function () {
    Recipient::factory()->withBirthday(11, 20)->create(['name' => 'Anna']);

    $this->artisan('birthdays:create')->assertSuccessful();

    expect(Occasion::query()->sole()->name)->toBe("Anna's birthday 2026");
});

it('reports how many birthday occasions it created', function () {
    Recipient::factory()->withBirthday(11, 20)->create();

    $this->artisan('birthdays:create')->expectsOutput('Created 1 birthday occasion(s).')->assertSuccessful();
});

it('schedules the birthday command to run daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'birthdays:create'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
});
