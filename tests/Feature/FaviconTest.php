<?php

declare(strict_types=1);

it('serves a favicon svg of the Klaus bow', function () {
    $svg = (string) file_get_contents(public_path('favicon.svg'));

    expect($svg)
        ->toContain('M12 11C9 5 3 5 3.5 9.5')
        ->not->toContain('M17.2 5.633')
        ->toContain('prefers-color-scheme: dark');
});

it('serves a 180 by 180 apple touch icon', function () {
    $size = getimagesize(public_path('apple-touch-icon.png'));

    expect(array_slice($size, 0, 2))->toBe([180, 180]);
});

it('serves a favicon ico that is not the Laravel one', function () {
    $bytes = (string) file_get_contents(public_path('favicon.ico'));

    expect(unpack('v', substr($bytes, 4, 2))[1])->toBe(3)
        ->and(sha1($bytes))->not->toBe('4de5db574e6c589ef763ce57c4061f2269648ca4');
});
