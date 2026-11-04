<?php

declare(strict_types=1);

it('shows someone who is not logged in what Klaus is for, with the Klaus logo, a way to log in and a way to create a household, and nothing left of the Laravel starter page', function () {
    $frontPage = aVisitorOpensKlaus();

    expect($frontPage)
        ->toTellWhatKlausIsFor()
        ->toOfferToLogInOrCreateAHousehold()
        ->toLeaveNothingOfTheLaravelStarterPage();
    expect($frontPage->brand())->toShowTheKlausLogo();
});
