<?php

use Voyager\System\Application;
use Voyager\System\Testing\Concerns\InteractsWithRedis;
use Voyager\Redis\RedisManager;

uses(InteractsWithRedis::class);

beforeEach(function () {
    $this->setUpRedis();
});

afterEach(function () {
    $this->tearDownRedis();
});

test('default configuration', function () {
    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);

    $predisClient = $this->redis['predis']->connection()->client();
    $parameters = $predisClient->getConnection()->getParameters();
    expect($parameters->scheme)->toBe('tcp');
    expect($parameters->host)->toEqual($host);
    expect($parameters->port)->toEqual($port);

    $phpRedisClient = $this->redis['phpredis']->connection()->client();
    expect($phpRedisClient->getHost())->toEqual($host);
    expect($phpRedisClient->getPort())->toEqual($port);
    expect($phpRedisClient->client('GETNAME'))->toBe('default');
});

test('url', function () {
    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);

    $predis = new RedisManager(new Application, 'predis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'url' => "redis://{$host}:{$port}",
            'database' => 5,
            'timeout' => 0.5,
        ],
    ]);
    $predisClient = $predis->connection()->client();
    $parameters = $predisClient->getConnection()->getParameters();
    expect($parameters->scheme)->toBe('tcp');
    expect($parameters->host)->toEqual($host);
    expect($parameters->port)->toEqual($port);

    $phpRedis = new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'url' => "redis://{$host}:{$port}",
            'database' => 5,
            'timeout' => 0.5,
        ],
    ]);
    $phpRedisClient = $phpRedis->connection()->client();
    expect($phpRedisClient->getHost())->toBe("tcp://{$host}");
    expect($phpRedisClient->getPort())->toEqual($port);
});

test('url with scheme', function () {
    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);

    $predis = new RedisManager(new Application, 'predis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'url' => "tls://{$host}:{$port}",
            'database' => 5,
            'timeout' => 0.5,
        ],
    ]);
    $predisClient = $predis->connection()->client();
    $parameters = $predisClient->getConnection()->getParameters();
    expect($parameters->scheme)->toBe('tls');
    expect($parameters->host)->toEqual($host);
    expect($parameters->port)->toEqual($port);

    $phpRedis = new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'url' => "tcp://{$host}:{$port}",
            'database' => 5,
            'timeout' => 0.5,
        ],
    ]);
    $phpRedisClient = $phpRedis->connection()->client();
    expect($phpRedisClient->getHost())->toBe("tcp://{$host}");
    expect($phpRedisClient->getPort())->toEqual($port);
});

test('scheme', function () {
    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);

    $predis = new RedisManager(new Application, 'predis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'scheme' => 'tls',
            'host' => $host,
            'port' => $port,
            'database' => 5,
            'timeout' => 0.5,
        ],
    ]);
    $predisClient = $predis->connection()->client();
    $parameters = $predisClient->getConnection()->getParameters();
    expect($parameters->scheme)->toBe('tls');
    expect($parameters->host)->toEqual($host);
    expect($parameters->port)->toEqual($port);

    $phpRedis = new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
            'database' => 5,
            'timeout' => 0.5,
        ],
    ]);
    $phpRedisClient = $phpRedis->connection()->client();
    expect($phpRedisClient->getHost())->toBe("tcp://{$host}");
    expect($phpRedisClient->getPort())->toEqual($port);
});

test('predis configuration with username', function () {
    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);
    $username = 'testuser';
    $password = 'testpw';

    $predis = new RedisManager(new Application, 'predis', [
        'default' => [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'database' => 5,
            'timeout' => 0.5,
        ],
    ]);
    $predisClient = $predis->connection()->client();
    $parameters = $predisClient->getConnection()->getParameters();
    expect($parameters->username)->toEqual($username);
    expect($parameters->password)->toEqual($password);
});

test('predis configuration with sentinel', function () {
    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);

    $predis = new RedisManager(new Application, 'predis', [
        'cluster' => false,
        'options' => [
            'replication' => 'sentinel',
            'service' => 'mymaster',
            'parameters' => [
                'default' => [
                    'database' => 5,
                ],
            ],
        ],
        'default' => [
            "tcp://{$host}:{$port}",
        ],
    ]);

    $predisClient = $predis->connection()->client();
    $parameters = $predisClient->getConnection()->getSentinelConnection()->getParameters();
    expect($parameters->host)->toEqual($host);
});

test('php redis tcp keepalive', function () {
    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);

    $phpRedis = new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'default' => [
            'host' => $host,
            'port' => $port,
            'database' => 5,
            'timeout' => 0.5,
            'tcp_keepalive' => 60,
        ],
    ]);

    $phpRedisClient = $phpRedis->connection()->client();
    expect($phpRedisClient->getOption(Redis::OPT_TCP_KEEPALIVE))->toEqual(1);
});

test('prefix override behaviour', function () {
    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);

    $predis1 = new RedisManager(new Application, 'predis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'scheme' => 'tls',
            'host' => $host,
            'port' => $port,
            'database' => 5,
            'timeout' => 0.5,
            'options' => [
                'prefix' => 'test_default_options_',
            ],
        ],
    ]);
    $predisClient1 = $predis1->client();
    expect($predisClient1->getOptions()->prefix->getPrefix())->toEqual('test_default_options_');

    $predis2 = new RedisManager(new Application, 'predis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'scheme' => 'tls',
            'host' => $host,
            'port' => $port,
            'database' => 5,
            'timeout' => 0.5,
            'options' => [
                'prefix' => 'test_default_options_',
            ],
            'prefix' => 'test_default_config_',
        ],
    ]);
    $predisClient2 = $predis2->client();
    expect($predisClient2->getOptions()->prefix->getPrefix())->toEqual('test_default_config_');

    $phpRedis1 = new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
            'database' => 5,
            'timeout' => 0.5,
            'options' => [
                'prefix' => 'test_default_options_',
            ],
        ],
    ]);
    $phpRedisClient1 = $phpRedis1->connection()->client();
    expect($phpRedisClient1->getOption(Redis::OPT_PREFIX))->toEqual('test_default_options_');

    $phpRedis2 = new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'options' => [
            'prefix' => 'test_',
        ],
        'default' => [
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
            'database' => 5,
            'timeout' => 0.5,
            'options' => [
                'prefix' => 'test_default_options_',
            ],
            'prefix' => 'test_default_config_',
        ],
    ]);
    $phpRedisClient2 = $phpRedis2->connection()->client();
    expect($phpRedisClient2->getOption(Redis::OPT_PREFIX))->toEqual('test_default_config_');
});
