<?php

declare(strict_types=1);

it('shows the Klaus bow in my browser tab and on my phone\'s home screen instead of the Laravel logo', function () {
    expect(theAppIcons())->toShowTheKlausBow();
});
