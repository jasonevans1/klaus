<?php

declare(strict_types=1);

use App\Models\User;

it('shows the Klaus bow mark in the sidebar', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('M12 11C9 5 3 5 3.5 9.5', false);
});

it('shows the Klaus bow mark on the login page', function () {
    $this->get(route('login'))->assertSee('M12 11C9 5 3 5 3.5 9.5', false);
});

it('no longer shows the Laravel mark anywhere in the app', function () {
    $this->get(route('login'))->assertDontSee('M17.2 5.633', false);
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertDontSee('M17.2 5.633', false);
});

it('names the brand Klaus even when the app name is something else', function () {
    config(['app.name' => 'Something Else']);

    $this->get(route('login'))->assertSee('Klaus');
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('Klaus');
});

it('draws the bow as an outline, not filled', function () {
    $html = $this->get(route('login'))->getContent();

    preg_match_all('/<path\b[^>]*>/', $html, $m);
    $paths = array_filter($m[0], fn ($p) => ! str_contains($p, 'fill-rule') && str_contains($p, 'M12 11'));
    expect($paths)->toHaveCount(4);
    foreach ($paths as $p) {
        expect($p)->toContain('fill="none"');
    }
});
