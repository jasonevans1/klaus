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

function logoSvgClasses(string $html): string
{
    preg_match_all('/<svg[^>]*class="([^"]*)"/', $html, $m);

    return implode(' ', array_filter($m[1], fn ($c) => str_contains($c, 'fill-current')));
}

it('draws the sidebar logo bow in the accent foreground colour', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->getContent();

    expect(logoSvgClasses($html))->toContain('text-accent-foreground');
});

it('draws the home page logo bow in the accent foreground colour', function () {
    expect(logoSvgClasses($this->get(route('home'))->getContent()))->toContain('text-accent-foreground');
});

it('no longer hard-codes a white or black bow on the logo tile', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->getContent();

    expect(logoSvgClasses($html))->not->toContain('text-white')->not->toContain('dark:text-black');
});

it('keeps the logo tile on the accent colour', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('bg-accent-content', false);
});
