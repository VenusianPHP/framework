<?php

use Voyager\Cache\RateLimiting\GlobalLimit;
use Voyager\Cache\RateLimiting\Limit;

test('the fluent constructors set decaySeconds and maxAttempts correctly', function () {
    $limit = new Limit('', 3, 1);
    expect($limit->decaySeconds)->toBe(1)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = Limit::perSecond(3);
    expect($limit->decaySeconds)->toBe(1)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = Limit::perSecond(3, 5);
    expect($limit->decaySeconds)->toBe(5)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = Limit::perMinute(3);
    expect($limit->decaySeconds)->toBe(60)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = Limit::perMinute(3, 4);
    expect($limit->decaySeconds)->toBe(240)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = Limit::perMinutes(2, 3);
    expect($limit->decaySeconds)->toBe(120)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = Limit::perHour(3);
    expect($limit->decaySeconds)->toBe(3600)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = Limit::perHour(3, 2);
    expect($limit->decaySeconds)->toBe(7200)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = Limit::perDay(3);
    expect($limit->decaySeconds)->toBe(86400)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = Limit::perDay(3, 5);
    expect($limit->decaySeconds)->toBe(432000)
        ->and($limit->maxAttempts)->toBe(3);

    $limit = new GlobalLimit(3);
    expect($limit->decaySeconds)->toBe(60)
        ->and($limit->maxAttempts)->toBe(3);
});
