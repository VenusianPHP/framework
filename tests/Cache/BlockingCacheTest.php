<?php

use Venusian\Tests\Cache\Fixtures\CacheApp;

dataset('stores', function () {
    yield 'array' => ['array'];
    yield 'database' => ['database'];
    yield 'file' => ['file'];

    if (CacheApp::redisReachable()) {
        yield 'redis' => ['redis'];
    }
});

beforeEach(fn () => $this->app = CacheApp::boot());
afterEach(fn () => CacheApp::tearDown($this->app, $this));

it('defaults to the file store', function () {
    expect($this->app['config']->get('cache.default'))->toBe('file')
        ->and(array_keys($this->app['config']->get('cache.stores')))->toBe(['array', 'database', 'file', 'memcached', 'redis']);
});

it('puts, gets, adds, counts and forgets', function (string $store) {
    $cache = $this->app->get('cache')->store($store);

    expect($cache->put('greeting', ['hello' => 'world'], 60))->toBeTrue()
        ->and($cache->get('greeting'))->toBe(['hello' => 'world'])
        ->and($cache->add('greeting', 'again', 60))->toBeFalse()
        ->and($cache->add('fresh', 'first', 60))->toBeTrue()
        ->and($cache->increment('counter', 5))->toBe(5)
        ->and($cache->decrement('counter', 2))->toBe(3)
        ->and($cache->remember('remembered', 60, fn () => 'computed'))->toBe('computed')
        ->and($cache->remember('remembered', 60, fn () => 'not again'))->toBe('computed')
        ->and($cache->pull('fresh'))->toBe('first')
        ->and($cache->has('fresh'))->toBeFalse()
        ->and($cache->forget('greeting'))->toBeTrue()
        ->and($cache->get('greeting', 'gone'))->toBe('gone');
})->with('stores');
