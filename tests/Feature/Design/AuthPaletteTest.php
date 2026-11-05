<?php

declare(strict_types=1);

function viewSource(string $path): string
{
    return (string) file_get_contents(resource_path('views/'.$path));
}

it('shows the login page on a dark wood background', function () {
    $this->get(route('login'))->assertOk()
        ->assertSee('dark:from-zinc-900', false)
        ->assertSee('dark:to-zinc-800', false)
        ->assertDontSee('neutral-', false);
});

it('shows the login page on snow in light mode', function () {
    $this->get(route('login'))->assertOk()->assertSee('bg-snow', false);
});

it('shows the register page with no neutral or stone greys', function () {
    $this->get(route('register'))->assertOk()
        ->assertDontSee('neutral-', false)
        ->assertDontSee('stone-', false);
});

it('keeps the sign-in pages dark by default', function () {
    $this->get(route('login'))->assertOk()->assertSee('class="dark"', false);
});

it('blends the passkey separator into the snow page', function () {
    expect(viewSource('components/passkey-verify.blade.php'))->toContain('bg-snow dark:bg-zinc-900');
});

it('uses no neutral or stone greys in the card and split auth layouts', function (string $layout) {
    expect(viewSource("layouts/auth/{$layout}.blade.php"))
        ->not->toContain('neutral-')
        ->not->toContain('stone-')
        ->toContain('bg-snow');
})->with(['card', 'split']);

it('shows the two-factor setup modal with no stone greys', function () {
    expect(viewSource('pages/settings/⚡two-factor-setup-modal.blade.php'))->not->toContain('stone-');
});
