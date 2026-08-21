<?php

use Tests\Redis\Fixtures\ConcurrencyLimiterMockThatDoesntRelease;
use Voyager\Contracts\Redis\LimiterTimeoutException;
use Voyager\System\Testing\Concerns\InteractsWithRedis;
use Voyager\Redis\Limiters\ConcurrencyLimiter;

uses(InteractsWithRedis::class);

beforeEach(function () {
    $this->setUpRedis();
});

afterEach(function () {
    $this->tearDownRedis();
});

/** The phpredis connection under test, driving the limiter directly against Redis. */
function concurrencyLimiterRedis(array $redisManagers)
{
    return $redisManagers['phpredis']->connection();
}

test('it locks tasks when no slot available', function () {
    $store = [];

    foreach (range(1, 2) as $i) {
        (new ConcurrencyLimiterMockThatDoesntRelease(concurrencyLimiterRedis($this->redis), 'key', 2, 5))->block(2, function () use (&$store, $i) {
            $store[] = $i;
        });
    }

    try {
        (new ConcurrencyLimiterMockThatDoesntRelease(concurrencyLimiterRedis($this->redis), 'key', 2, 5))->block(0, function () use (&$store) {
            $store[] = 3;
        });
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(LimiterTimeoutException::class);
    }

    (new ConcurrencyLimiterMockThatDoesntRelease(concurrencyLimiterRedis($this->redis), 'other_key', 2, 5))->block(2, function () use (&$store) {
        $store[] = 4;
    });

    expect($store)->toEqual([1, 2, 4]);
});

test('it releases lock after task finishes', function () {
    $store = [];

    foreach (range(1, 4) as $i) {
        (new ConcurrencyLimiter(concurrencyLimiterRedis($this->redis), 'key', 2, 5))->block(2, function () use (&$store, $i) {
            $store[] = $i;
        });
    }

    expect($store)->toEqual([1, 2, 3, 4]);
});

test('it releases lock if task took too long', function () {
    $store = [];

    $lock = (new ConcurrencyLimiterMockThatDoesntRelease(concurrencyLimiterRedis($this->redis), 'key', 1, 1));

    $lock->block(2, function () use (&$store) {
        $store[] = 1;
    });

    try {
        $lock->block(0, function () use (&$store) {
            $store[] = 2;
        });
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(LimiterTimeoutException::class);
    }

    usleep(1.2 * 1000000);

    $lock->block(0, function () use (&$store) {
        $store[] = 3;
    });

    expect($store)->toEqual([1, 3]);
});

test('it fails immediately or retries for a while based on a given timeout', function () {
    $store = [];

    $lock = (new ConcurrencyLimiterMockThatDoesntRelease(concurrencyLimiterRedis($this->redis), 'key', 1, 2));

    $lock->block(2, function () use (&$store) {
        $store[] = 1;
    });

    try {
        $lock->block(0, function () use (&$store) {
            $store[] = 2;
        });
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(LimiterTimeoutException::class);
    }

    $lock->block(3, function () use (&$store) {
        $store[] = 3;
    });

    expect($store)->toEqual([1, 3]);
});

test('it fails after retry timeout', function () {
    $store = [];

    $lock = (new ConcurrencyLimiterMockThatDoesntRelease(concurrencyLimiterRedis($this->redis), 'key', 1, 10));

    $lock->block(2, function () use (&$store) {
        $store[] = 1;
    });

    try {
        $lock->block(2, function () use (&$store) {
            $store[] = 2;
        });
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(LimiterTimeoutException::class);
    }

    expect($store)->toEqual([1]);
});

test('it releases if error is thrown', function () {
    $store = [];

    $lock = new ConcurrencyLimiter(concurrencyLimiterRedis($this->redis), 'key', 1, 5);

    try {
        $lock->block(1, function () {
            throw new Error;
        });
    } catch (Error) {
    }

    $lock = new ConcurrencyLimiter(concurrencyLimiterRedis($this->redis), 'key', 1, 5);
    $lock->block(1, function () use (&$store) {
        $store[] = 1;
    });

    expect($store)->toEqual([1]);
});
