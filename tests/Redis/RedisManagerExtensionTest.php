<?php

use Tests\Redis\Fixtures\FakeRedisConnector;
use Voyager\Contracts\Redis\Connector;
use Voyager\System\Application;
use Voyager\Redis\RedisManager;
use Mockery as m;

beforeEach(function () {
    $this->redis = new RedisManager(new Application, 'my_custom_driver', [
        'default' => [
            'host' => 'some-host',
            'port' => 'some-port',
            'database' => 5,
            'timeout' => 0.5,
        ],
        'clusters' => [
            'my-cluster' => [
                [
                    'host' => 'some-host',
                    'port' => 'some-port',
                    'database' => 5,
                    'timeout' => 0.5,
                ],
            ],
        ],
    ]);

    $this->redis->extend('my_custom_driver', function () {
        return new FakeRedisConnector;
    });
});

test('using custom redis connector with single redis instance', function () {
    expect($this->redis->resolve())->toBe('my-redis-connection');
});

test('using custom redis connector with redis cluster instance', function () {
    expect($this->redis->resolve('my-cluster'))->toBe('my-redis-cluster-connection');
});

test('parse connection configuration for cluster', function () {
    $name = 'my-cluster';
    $config = [
        [
            'url1',
            'url2',
            'url3',
        ],
    ];
    $redis = new RedisManager(new Application, 'my_custom_driver', [
        'clusters' => [
            $name => $config,
        ],
    ]);
    $redis->extend('my_custom_driver', function () use ($config) {
        return m::mock(Connector::class)
            ->shouldReceive('connectToCluster')
            ->once()
            ->withArgs(function ($configArg) use ($config) {
                return $config === $configArg;
            })
            ->getMock();
    });

    $redis->resolve($name);
});
