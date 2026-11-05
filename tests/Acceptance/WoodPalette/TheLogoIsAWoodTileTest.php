<?php

declare(strict_types=1);

it('shows the Klaus logo as a white bow on a wood tile by day and a dark wood bow on a gold tile at night, on the front page and while I plan gifts', function () {
    $me = signsUp('Jason', 'jason@example.com');

    expect(theLogoTileOn(aVisitorOpensKlaus()))->toBeWhiteOnWoodByDayAndDarkWoodOnGoldAtNight();
    expect(theLogoTileOn($me->homePage()))->toBeWhiteOnWoodByDayAndDarkWoodOnGoldAtNight();
});
