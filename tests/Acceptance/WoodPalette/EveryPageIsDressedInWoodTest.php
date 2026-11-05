<?php

declare(strict_types=1);

it('shows every page on snow by day and dark wood at night, in warm timber greys only, with wood buttons that turn gold at night, whether I am logging in, signing up or planning gifts', function () {
    $me = signsUp('Jason', 'jason@example.com');

    expect(theLookOf(aVisitorOpensKlaus()))->toBeDressedInWood();
    expect(theLookOf(theLoginPage()))->toBeDressedInWood();
    expect(theLookOf(theSignUpPage()))->toBeDressedInWood();
    expect(theLookOf($me->homePage()))->toBeDressedInWood();
});
