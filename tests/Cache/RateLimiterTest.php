<?php

use Carbon\Carbon;
use Voyager\Cache\RateLimiter;
use Venusian\Tests\Cache\Fixtures\CacheApp;

dataset('limiter stores', function () {
    yield 'array' => ['array'];
    yield 'database' => ['database'];
    yield 'file' => ['file'];

    if (CacheApp::redisReachable()) {
        yield 'redis' => ['redis'];
        yield 'redis, serializing' => ['redis-serializing'];
    }
});

beforeEach(function () {
    $this->app = CacheApp::boot();
    Carbon::setTestNow('2026-10-27 12:00:00');
});
afterEach(function () {
    Carbon::setTestNow();
    CacheApp::tearDown($this->app, $this);
});

/** A limiter over the named store; 'redis-serializing' is Redis with phpredis serialize and compress on. */
function limiterOn($app, string $store): RateLimiter
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

it('counts hits and refuses once the limit is reached, until the window passes', function (string $store) {
    $limiter = limiterOn($this->app, $store);

    expect($limiter->hit('login', 60))->toBe(1)
        ->and($limiter->hit('login', 60))->toBe(2)
        ->and((int) $limiter->attempts('login'))->toBe(2)
        ->and($limiter->tooManyAttempts('login', 3))->toBeFalse()
        ->and($limiter->remaining('login', 3))->toBe(1)
        ->and($limiter->retriesLeft('login', 3))->toBe(1)
        ->and($limiter->hit('login', 60))->toBe(3)
        ->and($limiter->tooManyAttempts('login', 3))->toBeTrue()
        ->and($limiter->remaining('login', 3))->toBe(0)
        ->and($limiter->availableIn('login'))->toBe(60);

    Carbon::setTestNow('2026-10-27 12:00:45');

    expect($limiter->availableIn('login'))->toBe(15);
})->with('limiter stores');

it('increments and decrements by an amount', function (string $store) {
    $limiter = limiterOn($this->app, $store);

    expect($limiter->increment('upload', 60, 5))->toBe(5)
        ->and($limiter->decrement('upload', 60, 2))->toBe(3)
        ->and((int) $limiter->attempts('upload'))->toBe(3);
})->with('limiter stores');

it('runs an attempt\'s callback while under the limit, and returns false once over it', function (string $store) {
    $limiter = limiterOn($this->app, $store);
    $runs = 0;

    $first = $limiter->attempt('send', 2, function () use (&$runs) { $runs++; return 'sent'; });
    $second = $limiter->attempt('send', 2, function () use (&$runs) { $runs++; });
    $third = $limiter->attempt('send', 2, function () use (&$runs) { $runs++; });

    expect([$first, $second, $third, $runs])->toBe(['sent', true, false, 2]);
})->with('limiter stores');

it('resets the attempts, and clears the timer too', function (string $store) {
    $limiter = limiterOn($this->app, $store);
    $limiter->hit('reset', 60);
    $limiter->hit('clear', 60);

    $limiter->resetAttempts('reset');
    $limiter->clear('clear');

    expect((int) $limiter->attempts('reset'))->toBe(0)
        ->and($limiter->availableIn('reset'))->toBe(60)
        ->and((int) $limiter->attempts('clear'))->toBe(0)
        ->and($limiter->availableIn('clear'))->toBe(0);
})->with('limiter stores');

it('cleans HTML entities out of keys', function () {
    expect(limiterOn($this->app, 'array')->cleanRateLimiterKey('café'))->toBe('cafe');
});
