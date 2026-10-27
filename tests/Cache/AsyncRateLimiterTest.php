<?php

use Voyager\Cache\ArrayStore;
use Voyager\Cache\Async\AwaitedRateLimiter;
use Voyager\Cache\RateLimiter;
use Voyager\Cache\Repository;
use Voyager\Contracts\IOPools\Promise;
use Venusian\Tests\Cache\Fixtures\CacheApp;

dataset('async limiter stores', function () {
    yield 'array' => ['array'];
    yield 'database' => ['database'];
    yield 'file' => ['file'];

    if (CacheApp::redisReachable()) {
        yield 'redis' => ['redis'];
        yield 'redis, serializing' => ['redis-serializing'];
    }
});

// The real clock throughout: the file and database stores do their I/O in worker processes,
// which a frozen Carbon in this one would not reach.
beforeEach(function () {
    $this->app = CacheApp::boot();
    $this->loop = $this->app->get('event-loop');
});
afterEach(fn () => CacheApp::tearDown($this->app, $this));

/** A limiter over the named store; 'redis-serializing' is Redis with phpredis serialize and compress on. */
function asyncLimiterOn($app, string $store): RateLimiter
{
    if ($store === 'redis-serializing') {
        $app['config']->set('database.redis.cache.options', [
            'serializer' => Redis::SERIALIZER_PHP,
            'compression' => defined('Redis::COMPRESSION_LZF') ? Redis::COMPRESSION_LZF : Redis::COMPRESSION_NONE,
        ]);
        $store = 'redis';
    }

    return new RateLimiter($app->get('cache')->store($store));
}

it('answers each operation as a promise with the blocking limiter\'s answer', function (string $store) {
    $async = asyncLimiterOn($this->app, $store)->async();
    $await = fn (Promise $promise) => $this->loop->await($promise);

    expect($async->hit('login', 60))->toBeInstanceOf(Promise::class)
        ->and($await($async->hit('login', 60)))->toBe(2)
        ->and((int) $await($async->attempts('login')))->toBe(2)
        ->and($await($async->tooManyAttempts('login', 3)))->toBeFalse()
        ->and($await($async->remaining('login', 3)))->toBe(1)
        ->and($await($async->retriesLeft('login', 3)))->toBe(1)
        ->and($await($async->hit('login', 60)))->toBe(3)
        ->and($await($async->tooManyAttempts('login', 3)))->toBeTrue()
        ->and($await($async->availableIn('login')))->toBeGreaterThan(0)->toBeLessThanOrEqual(60)
        ->and($await($async->increment('upload', 60, 5)))->toBe(5)
        ->and($await($async->decrement('upload', 60, 2)))->toBe(3);

    $await($async->resetAttempts('login'));
    expect((int) $await($async->attempts('login')))->toBe(0)
        ->and($await($async->availableIn('login')))->toBeGreaterThan(0)->toBeLessThanOrEqual(60);

    $await($async->clear('upload'));
    expect((int) $await($async->attempts('upload')))->toBe(0)
        ->and($await($async->availableIn('upload')))->toBe(0);
})->with('async limiter stores');

it('sees the blocking limiter\'s hits, and the blocking limiter sees its own', function (string $store) {
    $limiter = asyncLimiterOn($this->app, $store);
    $async = $limiter->async();

    $limiter->hit('mixed', 60);
    $after_blocking = $this->loop->await($async->hit('mixed', 60));

    $pending = $async->hit('mixed', 60);
    $blocking_sees = (int) $limiter->attempts('mixed');
    $this->loop->await($pending);

    expect($after_blocking)->toBe(2)
        ->and($blocking_sees)->toBe(3)
        ->and($limiter->tooManyAttempts('mixed', 3))->toBeTrue();
})->with('async limiter stores');

it('counts every hit when two land on one key at once', function (string $store) {
    $async = asyncLimiterOn($this->app, $store)->async();

    $first = $async->hit('together', 60);
    $second = $async->hit('together', 60);
    $hits = [$this->loop->await($first), $this->loop->await($second)];
    sort($hits);

    expect($hits)->toBe([1, 2])
        ->and((int) $this->loop->await($async->attempts('together')))->toBe(2);
})->with('async limiter stores');

it('runs an attempt\'s callback under the limit, awaiting a promise it returns', function () {
    $async = asyncLimiterOn($this->app, 'file')->async();
    $runs = 0;

    $first = $this->loop->await($async->attempt('send', 2, function () use (&$runs) {
        $runs++;

        return $this->loop->defer(fn () => 'sent later');
    }));
    $second = $this->loop->await($async->attempt('send', 2, function () use (&$runs) { $runs++; }));
    $third = $this->loop->await($async->attempt('send', 2, function () use (&$runs) { $runs++; }));

    expect([$first, $second, $third, $runs])->toBe(['sent later', true, false, 2]);
});

it('shares named limiters and key cleaning with the limiter it came from', function () {
    $limiter = asyncLimiterOn($this->app, 'array');
    $limiter->for('uploads', fn () => 'limit');

    expect(($limiter->async()->limiter('uploads'))())->toBe('limit')
        ->and($limiter->async()->cleanRateLimiterKey('café'))->toBe('cafe');
});

it('needs a cache with a loop', function () {
    expect(fn () => (new RateLimiter(new Repository(new ArrayStore)))->async())
        ->toThrow(RuntimeException::class, 'no loop');
});

it('refuses to work outside the loop task it was made for, instead of blocking', function () {
    $cache = $this->app->get('cache')->store('file');
    $escaped = $this->loop->await(AwaitedRateLimiter::run($this->loop, $cache, fn (AwaitedRateLimiter $limiter) => $limiter));

    expect(fn () => $escaped->hit('outside', 60))->toThrow(LogicException::class, 'only inside the loop task it was made for')
        ->and(fn () => $this->loop->await($this->loop->async(fn () => $escaped->attempts('elsewhere'))))
        ->toThrow(LogicException::class, 'only inside the loop task it was made for');
});
