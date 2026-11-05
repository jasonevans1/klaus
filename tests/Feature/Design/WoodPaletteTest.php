<?php

declare(strict_types=1);

function appCss(): string
{
    return (string) file_get_contents(resource_path('css/app.css'));
}

it('defines the warm wood zinc scale from the design system', function () {
    $scale = [
        50 => '#f3eee6', 100 => '#ede6db', 200 => '#e2d8ca', 300 => '#d0c3b1', 400 => '#a8977f', 500 => '#76664f',
        600 => '#5a4b39', 700 => '#453726', 800 => '#2c1e10', 900 => '#1f150a', 950 => '#14100b',
    ];

    foreach ($scale as $step => $hex) {
        expect(appCss())->toContain("--color-zinc-{$step}: {$hex};");
    }
});

it('sets the wood accent with white text in light mode', function () {
    expect(appCss())
        ->toContain('--color-accent: #54381b;')
        ->toContain('--color-accent-content: #54381b;')
        ->toContain('--color-accent-foreground: var(--color-white);');
});

it('sets the gold accent with dark wood text in dark mode', function () {
    preg_match('/@layer theme \{\s*\.dark \{(.*?)\}/s', appCss(), $m);

    expect($m[1] ?? '')
        ->toContain('--color-accent: #f1c779;')
        ->toContain('--color-accent-content: #f1c779;')
        ->toContain('--color-accent-foreground: #2c1e10;');
});

it('defines the snow colour for page backgrounds', function () {
    expect(appCss())->toContain('--color-snow: #f8faf5;');
});

it('uses the warm zinc-200 as the default border colour', function () {
    expect(appCss())
        ->toContain('border-color: var(--color-zinc-200, currentColor);')
        ->not->toContain('--color-gray-200');
});

it('keeps the red-600 danger button override', function () {
    expect(appCss())->toContain('background-color: var(--color-red-600);');
});
