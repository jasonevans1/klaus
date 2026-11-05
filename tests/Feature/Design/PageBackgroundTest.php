<?php

declare(strict_types=1);

use App\Models\User;

function appLayoutFiles(): array
{
    return [
        resource_path('views/layouts/app/sidebar.blade.php'),
        resource_path('views/layouts/app/header.blade.php'),
    ];
}

it('sets a snow background on signed-in pages in light mode', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('bg-snow', false);
});

it('keeps a dark wood background on signed-in pages in dark mode', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('dark:bg-zinc-800', false);
});

it('sets a snow background on the home page', function () {
    $this->get(route('home'))
        ->assertSee('bg-snow', false)
        ->assertSee('dark:bg-zinc-800', false);
});

it('no longer uses a white page background in the app layouts', function () {
    foreach (appLayoutFiles() as $file) {
        expect(file_get_contents($file))->not->toContain('<body class="min-h-screen bg-white');
    }
});
