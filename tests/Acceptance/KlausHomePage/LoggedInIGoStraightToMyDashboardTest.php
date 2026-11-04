<?php

declare(strict_types=1);

it('takes me straight to my dashboard when I open Klaus while I am logged in', function () {
    $me = signsUp('Jason', 'jason@example.com');

    expect($me->opensKlaus())->toBeMyDashboard();
});
