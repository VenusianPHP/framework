<?php

use Venusian\Tests\Redis\Fixtures\RedisApp;
use Voyager\Redis\Limiters\DurationLimiter;

beforeEach(function () {
    RedisApp::reachable() || $this->markTestSkipped('needs a Redis on 127.0.0.1:6379');
    $this->app = RedisApp::boot();
});
afterEach(fn () => isset($this->app) && RedisApp::tearDown($this->app, $this));

it('reports the decay time and every attempt left for a key never hit', function () {
    $limiter = new DurationLimiter($this->app->get('redis')->connection(), 'fresh', 3, 60);
    $before = time();

    expect($limiter->tooManyAttempts())->toBeFalse()
        ->and($limiter->decaysAt)->toBeGreaterThanOrEqual($before + 60)->toBeLessThanOrEqual(time() + 60)
        ->and($limiter->remaining)->toBe(3);
});

it('counts down the attempts left inside the window, then reports too many', function () {
    $limiter = new DurationLimiter($this->app->get('redis')->connection(), 'busy', 2, 60);

    expect($limiter->acquire())->toBeTrue()
        ->and($limiter->tooManyAttempts())->toBeFalse()
        ->and((int) $limiter->remaining)->toBe(1)
        ->and($limiter->acquire())->toBeTrue()
        ->and($limiter->tooManyAttempts())->toBeTrue()
        ->and((int) $limiter->decaysAt)->toBeGreaterThan(time());
});
