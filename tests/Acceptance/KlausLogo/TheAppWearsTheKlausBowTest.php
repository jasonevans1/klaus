<?php

declare(strict_types=1);

it('shows the Klaus name and the bow, drawn as an outline, when I log in and while I plan gifts, even though the app is still called Laravel on the server', function () {
    theAppIsStillCalled('Laravel');
    $me = signsUp('Jason', 'jason@example.com');

    expect(theLoginPage())->toShowTheKlausLogo();
    expect($me->homePage())->toShowTheKlausLogo();
});
