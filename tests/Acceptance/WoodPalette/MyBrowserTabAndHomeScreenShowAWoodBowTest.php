<?php

declare(strict_types=1);

it('shows the bow in wood in my browser tab, in gold on a dark tab bar, and as a white bow on a wood tile on my phone\'s home screen', function () {
    expect(theAppIconColours())->toBeInWood();
});
