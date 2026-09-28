<?php

use Venusian\Tests\Redis\Fixtures\RedisApp;

beforeEach(function () {
    RedisApp::reachable() || $this->markTestSkipped('needs a Redis on 127.0.0.1:6379');
    $this->app = RedisApp::boot();
});
afterEach(fn () => isset($this->app) && RedisApp::tearDown($this->app, $this));

it('runs blocking commands on the default connection', function () {
    $redis = $this->app->get('redis')->connection();

    $redis->set('greeting', 'hello');
    $redis->rpush('list', 'a', 'b');

    expect($redis->get('greeting'))->toBe('hello')
        ->and($redis->lrange('list', 0, -1))->toBe(['a', 'b'])
        ->and($redis->client()->getDbNum())->toBe(RedisApp::DATABASE);
});
