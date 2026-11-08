<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\Occasion;
use App\Models\Recipient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('has no birthdays:create command', function () {
    expect(Artisan::all())->not->toHaveKey('birthdays:create');
});

it('schedules no daily birthday job', function () {
    $commands = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

    expect($commands)->not->toContain('birthdays:create');
});

it('no longer has a last_birthday_year column on recipients', function () {
    expect(Schema::hasColumn('recipients', 'last_birthday_year'))->toBeFalse();
});

it('keeps existing per-person birthday occasions and their gifts', function () {
    $recipient = Recipient::factory()->create();
    $occasion = Occasion::factory()->birthday($recipient, '2026-12-01')->create();
    $gift = Gift::factory()->create(['occasion_id' => $occasion->id, 'recipient_id' => $recipient->id]);

    expect($occasion->fresh()->birthdayRecipient->is($recipient))->toBeTrue()
        ->and($gift->fresh())->not->toBeNull();
});
