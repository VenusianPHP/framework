<?php

use Voyager\Cache\ArrayStore;
use Voyager\Cache\RateLimiter;
use Voyager\Contracts\Cache\Repository as Cache;

test('tooManyAttempts returns true if already locked out', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('get')->once()->with('key', 0)->andReturn(1);
    $cache->shouldReceive('has')->once()->with('key:timer')->andReturn(true);
    $cache->shouldReceive('add')->never();
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    expect($rateLimiter->tooManyAttempts('key', 1))->toBeTrue();
});

test('hit properly increments the attempt count', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('add')->once()->with('key:timer', Mockery::type('int'), 1)->andReturn(true);
    $cache->shouldReceive('add')->once()->with('key', 0, 1)->andReturn(true);
    $cache->shouldReceive('increment')->once()->with('key', 1)->andReturn(1);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    $rateLimiter->hit('key', 1);
});

test('increment properly increments the attempt count', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('add')->once()->with('key:timer', Mockery::type('int'), 1)->andReturn(true);
    $cache->shouldReceive('add')->once()->with('key', 0, 1)->andReturn(true);
    $cache->shouldReceive('increment')->once()->with('key', 5)->andReturn(5);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    $rateLimiter->increment('key', 1, 5);
});

test('decrement properly decrements the attempt count', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('add')->once()->with('key:timer', Mockery::type('int'), 1)->andReturn(true);
    $cache->shouldReceive('add')->once()->with('key', 0, 1)->andReturn(true);
    $cache->shouldReceive('increment')->once()->with('key', -5)->andReturn(-5);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    $rateLimiter->decrement('key', 1, 5);
});

test('hit has no memory leak', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('add')->once()->with('key:timer', Mockery::type('int'), 1)->andReturn(true);
    $cache->shouldReceive('add')->once()->with('key', 0, 1)->andReturn(false);
    $cache->shouldReceive('increment')->once()->with('key', 1)->andReturn(1);
    $cache->shouldReceive('put')->once()->with('key', 1, 1);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    $rateLimiter->hit('key', 1);
});

test('increment with a custom amount has no memory leak', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('add')->once()->with('key:timer', Mockery::type('int'), 60)->andReturn(true);
    $cache->shouldReceive('add')->once()->with('key', 0, 60)->andReturn(false);
    $cache->shouldReceive('increment')->once()->with('key', 2)->andReturn(2);
    $cache->shouldReceive('put')->once()->with('key', 2, 60);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    $rateLimiter->increment('key', 60, 2);
});

test('remaining is not negative', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('get')->with('key', 0)->andReturn(5);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);

    $rateLimiter = new RateLimiter($cache);

    expect($rateLimiter->remaining('key', 3))->toBe(0)
        ->and($rateLimiter->retriesLeft('key', 3))->toBe(0);
});

test('retriesLeft returns the correct count', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('get')->once()->with('key', 0)->andReturn(3);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    expect($rateLimiter->retriesLeft('key', 5))->toEqual(2);
});

test('clear clears the cache keys', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('forget')->once()->with('key');
    $cache->shouldReceive('forget')->once()->with('key:timer');
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    $rateLimiter->clear('key');
});

test('availableIn returns positive values', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('get')->andReturn(now()->subSeconds(60)->getTimestamp(), null);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    expect($rateLimiter->availableIn('key:timer'))->toBeGreaterThanOrEqual(0)
        ->and($rateLimiter->availableIn('key:timer'))->toBeGreaterThanOrEqual(0);
});

test('attempt runs the callback and returns true', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('get')->once()->with('key', 0)->andReturn(0);
    $cache->shouldReceive('add')->once()->with('key:timer', Mockery::type('int'), 1);
    $cache->shouldReceive('add')->once()->with('key', 0, 1)->andReturns(1);
    $cache->shouldReceive('increment')->once()->with('key', 1)->andReturn(1);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);

    $executed = false;

    $rateLimiter = new RateLimiter($cache);

    $rateLimiter->attempt('key', 1, function () use (&$executed) {
        $executed = true;
    }, 1);

    expect($executed)->toBeTrue();
});

test('attempt returns the callback return value', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('get')->times(6)->with('key', 0)->andReturn(0);
    $cache->shouldReceive('add')->times(6)->with('key:timer', Mockery::type('int'), 1);
    $cache->shouldReceive('add')->times(6)->with('key', 0, 1)->andReturns(1);
    $cache->shouldReceive('increment')->times(6)->with('key', 1)->andReturn(1);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);

    $rateLimiter = new RateLimiter($cache);

    expect($rateLimiter->attempt('key', 1, function () {
        return 'foo';
    }, 1))->toBe('foo');

    expect($rateLimiter->attempt('key', 1, function () {
        return false;
    }, 1))->toBe(false);

    expect($rateLimiter->attempt('key', 1, function () {
        return [];
    }, 1))->toBe([]);

    expect($rateLimiter->attempt('key', 1, function () {
        return 0;
    }, 1))->toBe(0);

    expect($rateLimiter->attempt('key', 1, function () {
        return 0.0;
    }, 1))->toBe(0.0);

    expect($rateLimiter->attempt('key', 1, function () {
        return '';
    }, 1))->toBe('');
});

test('attempt returns false when out of attempts', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('get')->once()->with('key', 0)->andReturn(2);
    $cache->shouldReceive('has')->once()->with('key:timer')->andReturn(true);
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);

    $executed = false;

    $rateLimiter = new RateLimiter($cache);

    $result = $rateLimiter->attempt('key', 1, function () use (&$executed) {
        $executed = true;
    }, 1);

    expect($result)->toBeFalse()
        ->and($executed)->toBeFalse();
});

test('keys are sanitized from unicode characters', function () {
    $cache = Mockery::mock(Cache::class);
    $cache->shouldReceive('get')->once()->with('john', 0)->andReturn(1);
    $cache->shouldReceive('has')->once()->with('john:timer')->andReturn(true);
    $cache->shouldReceive('add')->never();
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);
    $rateLimiter = new RateLimiter($cache);

    expect($rateLimiter->tooManyAttempts('jôhn', 1))->toBeTrue();
});

test('a key is sanitized only once', function () {
    $cache = Mockery::mock(Cache::class);
    $rateLimiter = new RateLimiter($cache);

    $key = "john'doe";
    $cleanedKey = $rateLimiter->cleanRateLimiterKey($key);

    $cache->shouldReceive('get')->once()->with($cleanedKey, 0)->andReturn(1);
    $cache->shouldReceive('has')->once()->with("$cleanedKey:timer")->andReturn(true);
    $cache->shouldReceive('add')->never();
    $cache->shouldReceive('getStore')->andReturn(new ArrayStore);

    expect($rateLimiter->tooManyAttempts($key, 1))->toBeTrue();
});
