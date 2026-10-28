<?php

declare(strict_types=1);

it('gives someone who signs up without an invite a household of their own', function () {
    $me = signsUp('Jason', 'jason@example.com');
    $robin = signsUp('Robin', 'robin@example.com');

    expect(householdOf($robin))
        ->toBeNamed("Robin's household")
        ->toHaveMembers(['Robin']);
    expect(householdOf($me))
        ->toBeNamed("Jason's household")
        ->toHaveMembers(['Jason']);
});
