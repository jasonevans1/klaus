<?php

declare(strict_types=1);

it('puts my partner in my household when they sign up from my invite', function () {
    $me = signsUp('Jason', 'jason@example.com');

    expect($me->invites('sam@example.com'))->toHaveBeenSent();
    expect(invitesSentTo('sam@example.com'))->toBe(1);

    $sam = acceptsInvite(to: 'sam@example.com', name: 'Sam');

    expect(householdOf($me))
        ->toBeNamed("Jason's household")
        ->toHaveMembers(['Jason', 'Sam']);
    expect(householdOf($sam))->toBeTheSameHouseholdAs(householdOf($me));
});
