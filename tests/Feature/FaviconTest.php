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

it('draws the favicon svg bow in wood', function () {
    expect((string) file_get_contents(public_path('favicon.svg')))->toContain('stroke="#54381b"');
});

it('switches the favicon svg bow to gold in dark mode', function () {
    expect((string) file_get_contents(public_path('favicon.svg')))->toContain('path{stroke:#f1c779}');
});

it('no longer uses the old ink colour in the favicon svg', function () {
    expect((string) file_get_contents(public_path('favicon.svg')))->not->toContain('#262626');
});

it('puts the home screen icon on a wood background', function () {
    $img = imagecreatefrompng(public_path('apple-touch-icon.png'));
    imagepalettetotruecolor($img);

    foreach ([[0, 0], [179, 0], [0, 179], [179, 179]] as [$x, $y]) {
        $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
        expect([$c['red'], $c['green'], $c['blue'], $c['alpha']])->toBe([0x54, 0x38, 0x1B, 0]);
    }
});

it('keeps the favicon ico at 16, 32 and 48 pixels', function () {
    $bytes = (string) file_get_contents(public_path('favicon.ico'));
    $widths = [];
    foreach (range(0, 2) as $i) {
        $w = ord($bytes[6 + $i * 16]);
        $widths[] = $w === 0 ? 256 : $w;
    }

    expect($widths)->toBe([16, 32, 48]);
});
