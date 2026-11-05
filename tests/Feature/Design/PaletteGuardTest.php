<?php

declare(strict_types=1);

function offPaletteClasses(string $source): array
{
    $utilities = 'ring-offset|bg|text|border|from|via|to|ring|divide|fill|stroke|outline|shadow|placeholder|decoration';
    preg_match_all('/(?<![\w-])(?:[\w-]+:)*(?:'.$utilities.')-(?:stone|neutral|gray|slate)-\d{2,3}(?:\/\d+)?/', $source, $matches);

    return $matches[0];
}

it('uses no stone, neutral, gray or slate colour classes in the views', function () {
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        foreach (offPaletteClasses((string) file_get_contents($file->getPathname())) as $class) {
            $found[] = $file->getPathname().': '.$class;
        }
    }

    expect($found)->toBe([]);
});

it('lists every off-palette colour class it finds', function () {
    $source = '<div class="bg-stone-800 dark:text-neutral-400 hover:border-gray-200/50 text-slate-500">neutral</div>';

    expect(offPaletteClasses($source))->toBe([
        'bg-stone-800',
        'dark:text-neutral-400',
        'hover:border-gray-200/50',
        'text-slate-500',
    ]);
});

it('allows zinc, accent, snow and the status colours', function () {
    $source = '<div class="bg-zinc-800 dark:text-zinc-400 bg-snow text-accent bg-red-500 text-purple-600 bg-white">neutral</div>';

    expect(offPaletteClasses($source))->toBe([]);
});
