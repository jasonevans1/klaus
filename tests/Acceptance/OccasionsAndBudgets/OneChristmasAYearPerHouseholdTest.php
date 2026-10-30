<?php

declare(strict_types=1);

it('keeps one Christmas a year per household and never shows mine to another household', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $neighbour = signsUp('Ned', 'ned@example.com');

    $me->startsChristmas(2025);
    $me->startsChristmas(2026);

    expect($me->startsChristmas(2026))->toBeRefusedWith('You already have Christmas 2026.');

    $neighbour->startsChristmas(2026);

    expect($me->occasions())->toBe(['Christmas 2026', 'Christmas 2025']);
    expect($neighbour->occasions())->toBe(['Christmas 2026']);
});
