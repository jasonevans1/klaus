<?php

declare(strict_types=1);

it('refuses to invite someone who already has a Klaus account', function () {
    $me = signsUp('Jason', 'jason@example.com');
    signsUp('Alex', 'alex@example.com');

    expect($me->invites('Alex@Example.com'))->toBeRefusedWith('That email already has a Klaus account.');
    expect(invitesSentTo('alex@example.com'))->toBe(0);
    expect(householdOf($me))->toHaveMembers(['Jason']);
});
