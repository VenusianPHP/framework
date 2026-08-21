<?php

use Tests\Cache\Fixtures\ConcurrencyLimiterBackedEnum;
use Tests\Cache\Fixtures\ConcurrencyLimiterMockThatDoesntRelease;
use Tests\Cache\Fixtures\ConcurrencyLimiterUnitEnum;
use Voyager\Cache\ArrayStore;
use Voyager\Cache\Limiters\ConcurrencyLimiter;
use Voyager\Cache\Limiters\LimiterTimeoutException;
use Voyager\Cache\Repository;
use Voyager\Contracts\Cache\LockProvider;
use Voyager\Contracts\Cache\Store;

beforeEach(function () {
    $this->repository = new Repository(new ArrayStore);
});

test('it locks tasks when no slot is available', function () {
    $store = [];

    foreach (range(1, 2) as $i) {
        (new ConcurrencyLimiterMockThatDoesntRelease($this->repository->getStore(), 'key', 2, 5))->block(2, function () use (&$store, $i) {
            $store[] = $i;
        });
    }

    try {
        (new ConcurrencyLimiterMockThatDoesntRelease($this->repository->getStore(), 'key', 2, 5))->block(0, function () use (&$store) {
            $store[] = 3;
        });
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(LimiterTimeoutException::class);
    }

    (new ConcurrencyLimiterMockThatDoesntRelease($this->repository->getStore(), 'other_key', 2, 5))->block(2, function () use (&$store) {
        $store[] = 4;
    });

    expect($store)->toEqual([1, 2, 4]);
});

test('it releases the lock after the task finishes', function () {
    $store = [];

    foreach (range(1, 4) as $i) {
        (new ConcurrencyLimiter($this->repository->getStore(), 'key', 2, 5))->block(2, function () use (&$store, $i) {
            $store[] = $i;
        });
    }

    expect($store)->toEqual([1, 2, 3, 4]);
});

test('it releases the lock if the task took too long', function () {
    $store = [];

    $lock = new ConcurrencyLimiterMockThatDoesntRelease($this->repository->getStore(), 'key', 1, 1);

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

    $lock = new ConcurrencyLimiterMockThatDoesntRelease($this->repository->getStore(), 'key', 1, 2);

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

test('it fails after the retry timeout', function () {
    $store = [];

    $lock = new ConcurrencyLimiterMockThatDoesntRelease($this->repository->getStore(), 'key', 1, 10);

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

test('it releases if an error is thrown', function () {
    $store = [];

    $lock = new ConcurrencyLimiter($this->repository->getStore(), 'key', 1, 5);

    try {
        $lock->block(1, function () {
            throw new Error;
        });
    } catch (Error) {
    }

    $lock = new ConcurrencyLimiter($this->repository->getStore(), 'key', 1, 5);
    $lock->block(1, function () use (&$store) {
        $store[] = 1;
    });

    expect($store)->toEqual([1]);
});

test('the funnel method on the repository', function () {
    $store = [];

    $result = $this->repository->funnel('test-funnel')
        ->limit(2)
        ->releaseAfter(5)
        ->block(2)
        ->then(function () use (&$store) {
            $store[] = 1;

            return 'ok';
        });

    expect($store)->toEqual([1])
        ->and($result)->toBe('ok');
});

test('the funnel method accepts a BackedEnum', function () {
    $store = [];

    $result = $this->repository->funnel(ConcurrencyLimiterBackedEnum::TestFunnel)
        ->limit(2)
        ->releaseAfter(5)
        ->block(2)
        ->then(function () use (&$store) {
            $store[] = 1;

            return 'ok';
        });

    expect($store)->toEqual([1])
        ->and($result)->toBe('ok');
});

test('the funnel method accepts a UnitEnum', function () {
    $store = [];

    $result = $this->repository->funnel(ConcurrencyLimiterUnitEnum::TestFunnel)
        ->limit(2)
        ->releaseAfter(5)
        ->block(2)
        ->then(function () use (&$store) {
            $store[] = 1;

            return 'ok';
        });

    expect($store)->toEqual([1])
        ->and($result)->toBe('ok');
});

test('a funnel BackedEnum shares its key with the string equivalent', function () {
    // Fill all slots using the backed enum's string value
    foreach (range(1, 2) as $i) {
        (new ConcurrencyLimiterMockThatDoesntRelease($this->repository->getStore(), 'test-funnel', 2, 5))->block(2, function () {
        });
    }

    // Try to acquire via the BackedEnum — should conflict with the string key
    $result = $this->repository->funnel(ConcurrencyLimiterBackedEnum::TestFunnel)
        ->limit(2)
        ->releaseAfter(5)
        ->block(0)
        ->then(
            function () {
                return 'success';
            },
            function () {
                return 'failed';
            }
        );

    expect($result)->toBe('failed');
});

test('the funnel method throws an exception when the store does not support locks', function () {
    $store = $this->createMock(Store::class);
    $repository = new Repository($store);

    expect($store)->not->toBeInstanceOf(LockProvider::class);

    $this->expectException(BadMethodCallException::class);
    $this->expectExceptionMessage('This cache store does not support locks.');

    $repository->funnel('test');
});

test('the funnel method accepts a failure callback', function () {
    $store = [];

    // Fill all slots without releasing
    foreach (range(1, 2) as $i) {
        (new ConcurrencyLimiterMockThatDoesntRelease($this->repository->getStore(), 'funnel-key', 2, 5))->block(2, function () use (&$store, $i) {
            $store[] = $i;
        });
    }

    // Try to acquire when all slots are full
    $result = $this->repository->funnel('funnel-key')
        ->limit(2)
        ->releaseAfter(5)
        ->block(0)
        ->then(
            function () use (&$store) {
                $store[] = 'success';
            },
            function ($e) use (&$store) {
                expect($e)->toBeInstanceOf(LimiterTimeoutException::class);
                $store[] = 'failed';

                return 'failure-result';
            }
        );

    expect($store)->toEqual([1, 2, 'failed'])
        ->and($result)->toBe('failure-result');
});
