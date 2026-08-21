<?php

use Voyager\Contracts\Redis\LimiterTimeoutException;
use Voyager\System\Testing\Concerns\InteractsWithRedis;
use Voyager\Redis\Limiters\DurationLimiter;

uses(InteractsWithRedis::class);

beforeEach(function () {
    $this->setUpRedis();
});

afterEach(function () {
    $this->tearDownRedis();
});

/** The phpredis connection under test, driving the limiter directly against Redis. */
function durationLimiterRedis(array $redisManagers)
{
    return $redisManagers['phpredis']->connection();
}

test('it locks tasks when no slot available', function () {
    $store = [];

    (new DurationLimiter(durationLimiterRedis($this->redis), 'key', 2, 2))->block(0, function () use (&$store) {
        $store[] = 1;
    });

    (new DurationLimiter(durationLimiterRedis($this->redis), 'key', 2, 2))->block(0, function () use (&$store) {
        $store[] = 2;
    });

    try {
        (new DurationLimiter(durationLimiterRedis($this->redis), 'key', 2, 2))->block(0, function () use (&$store) {
            $store[] = 3;
        });
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(LimiterTimeoutException::class);
    }

    expect($store)->toEqual([1, 2]);

    sleep(2);

    (new DurationLimiter(durationLimiterRedis($this->redis), 'key', 2, 2))->block(0, function () use (&$store) {
        $store[] = 3;
    });

    expect($store)->toEqual([1, 2, 3]);
});

test('it fails immediately or retries for a while based on a given timeout', function () {
    $store = [];

    (new DurationLimiter(durationLimiterRedis($this->redis), 'key', 1, 1))->block(2, function () use (&$store) {
        $store[] = 1;
    });

    try {
        (new DurationLimiter(durationLimiterRedis($this->redis), 'key', 1, 1))->block(0, function () use (&$store) {
            $store[] = 2;
        });
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(LimiterTimeoutException::class);
    }

    (new DurationLimiter(durationLimiterRedis($this->redis), 'key', 1, 1))->block(2, function () use (&$store) {
        $store[] = 3;
    });

    expect($store)->toEqual([1, 3]);
});

test('it returns the callback result', function () {
    $limiter = new DurationLimiter(durationLimiterRedis($this->redis), 'key', 1, 1);

    $result = $limiter->block(1, function () {
        return 'foo';
    });

    expect($result)->toBe('foo');
});

test('acquire sets decays at and remaining', function () {
    $limiter = new DurationLimiter(durationLimiterRedis($this->redis), 'acquire-key', 2, 2);

    $acquired1 = $limiter->acquire();
    expect($acquired1)->toBeTrue();
    expect($limiter->decaysAt)->toBeGreaterThanOrEqual(time());
    expect($limiter->remaining)->toBe(1);

    $acquired2 = $limiter->acquire();
    expect($acquired2)->toBeTrue();
    expect($limiter->remaining)->toBe(0);

    $acquired3 = $limiter->acquire();
    expect($acquired3)->toBeFalse();
    expect($limiter->remaining)->toBe(0);
});

test('too many attempts reports correctly', function () {
    $limiter = new DurationLimiter(durationLimiterRedis($this->redis), 'too-many-key', 2, 1);

    // Initially, should not have too many attempts
    expect($limiter->tooManyAttempts())->toBeFalse();
    expect($limiter->decaysAt)->toBe(0); // As per script for non-existing key
    expect($limiter->remaining)->toBeGreaterThan(0); // Remaining is positive future timestamp placeholder

    // Use up the available slots
    expect($limiter->acquire())->toBeTrue();
    expect($limiter->acquire())->toBeTrue();

    // Now, too many attempts within the same window
    expect($limiter->tooManyAttempts())->toBeTrue();
    expect(max(0, $limiter->remaining))->toBe(0);

    // After decay window, attempts should be allowed again
    sleep(1);
    expect($limiter->tooManyAttempts())->toBeFalse();
});

test('clear resets limiter', function () {
    $limiter = new DurationLimiter(durationLimiterRedis($this->redis), 'clear-key', 1, 2);

    expect($limiter->acquire())->toBeTrue();
    expect($limiter->acquire())->toBeFalse();

    // Clear and try again
    $limiter->clear();
    expect($limiter->acquire())->toBeTrue();
});

test('block returns true without callback', function () {
    $limiter = new DurationLimiter(durationLimiterRedis($this->redis), 'no-callback-key', 1, 1);

    expect($limiter->block(1))->toBeTrue();
});

test('acquire resets after decay', function () {
    $limiter = new DurationLimiter(durationLimiterRedis($this->redis), 'reset-after-decay-key', 1, 1);

    expect($limiter->acquire())->toBeTrue();
    expect($limiter->acquire())->toBeFalse();

    sleep(1);

    expect($limiter->acquire())->toBeTrue();
    expect($limiter->remaining)->toBe(0);
});
