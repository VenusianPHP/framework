<?php

use Voyager\Contracts\IOPools\Loop;
use Voyager\Redis\Sockets\RedisSocketException;
use Venusian\Tests\Redis\Fixtures\RedisApp;

beforeEach(function () {
    RedisApp::reachable() || $this->markTestSkipped('needs a Redis on 127.0.0.1:6379');
    $this->app = RedisApp::boot();
    $this->redis = $this->app->get('redis');
});
afterEach(fn () => isset($this->app) && RedisApp::tearDown($this->app, $this));

it('answers each command with its own reply, in the order they were sent', function () {
    $pipe = $this->redis->pipe();
    $key = $pipe->prefix().'piped';

    $set = $pipe->send(['SET', $key, 'value'], 'SET piped');
    $get = $pipe->send(['GET', $key], 'GET piped');
    $count = $pipe->send(['INCRBY', $key.'-count', '7'], 'INCRBY piped-count');
    $missing = $pipe->send(['GET', $key.'-missing'], 'GET piped-missing');

    expect($set->wait())->toBe('OK')
        ->and($get->wait())->toBe('value')
        ->and($count->wait())->toBe(7)
        ->and($missing->wait())->toBeNull()
        ->and($this->redis->connection()->get('piped'))->toBe('value');
});

it('rejects the command Redis refuses and answers the ones after it', function () {
    $pipe = $this->redis->pipe();
    $this->redis->connection()->set('text', 'not a number');

    $refused = $pipe->send(['INCR', $pipe->prefix().'text'], 'INCR text');
    $after = $pipe->send(['GET', $pipe->prefix().'text'], 'GET text');

    expect(fn () => $refused->wait())->toThrow(RedisSocketException::class, 'INCR text failed: ERR value is not an integer')
        ->and($after->wait())->toBe('not a number');
});

it('leaves the loop once every reply is in', function () {
    $pipe = $this->redis->pipe();

    $pipe->send(['PING'], 'PING')->wait();

    expect($this->app->get(Loop::class)->run())->toBe(0);
});
