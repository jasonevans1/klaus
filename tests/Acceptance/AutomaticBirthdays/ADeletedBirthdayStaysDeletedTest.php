<?php

declare(strict_types=1);

it('keeps a birthday I deleted gone this year, and brings it back next year', function () {
    itIs('2026-11-01');
    $me = signsUp('Jason', 'jason@example.com');
    $me->adds(person('Pluto')->in('Family')->born(11, 20));

    theDailyJobRuns();
    $me->deletesOccasion("Pluto's birthday 2026");

    itIs('2026-11-02');
    theDailyJobRuns();
    itIs('2026-11-19');
    theDailyJobRuns();

    expect($me->occasions())->toBe([]);

    itIs('2027-11-01');
    theDailyJobRuns();

    expect($me->occasions())->toBe(["Pluto's birthday 2027"]);
});
