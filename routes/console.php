<?php

use App\Actions\Occasions\CreateUpcomingBirthdays;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('birthdays:create', function (CreateUpcomingBirthdays $action): void {
    $count = $action->handle(CarbonImmutable::today());

    $this->info("Created {$count} birthday occasion(s).");
})->purpose('Create occasions for birthdays in the next 30 days');

Schedule::command('birthdays:create')->daily();
