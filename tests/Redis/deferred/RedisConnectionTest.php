<?php

use Voyager\Contracts\Events\Dispatcher;
use Voyager\System\Application;
use Voyager\System\Testing\Concerns\InteractsWithRedis;
use Voyager\Redis\Connections\Connection;
use Voyager\Redis\Connections\PhpRedisConnection;
use Voyager\Redis\RedisManager;
use Mockery as m;
use Predis\Client;

uses(InteractsWithRedis::class);

beforeEach(function () {
    $this->setUpRedis();
});

afterEach(function () {
    $this->tearDownRedis();
});

/**
 * Every Redis connection under test — Predis, PhpRedis and every PhpRedis
 * option variant (URL, persistent, serializers, compression, scan mode).
 */
function redisConnections(array $redisManagers): array
{
    $connections = [
        'predis' => $redisManagers['predis']->connection(),
        'phpredis' => $redisManagers['phpredis']->connection(),
    ];

    $host = env('REDIS_HOST', '127.0.0.1');
    $port = env('REDIS_PORT', 6379);

    $connections[] = (new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'default' => [
            'url' => "redis://user@$host:$port",
            'host' => 'overwrittenByUrl',
            'port' => 'overwrittenByUrl',
            'database' => 5,
            'options' => ['prefix' => 'laravel:'],
            'timeout' => 0.5,
        ],
    ]))->connection();

    $connections['persistent'] = (new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'default' => [
            'host' => $host,
            'port' => $port,
            'database' => 6,
            'options' => ['prefix' => 'laravel:'],
            'timeout' => 0.5,
            'persistent' => true,
            'persistent_id' => 'laravel',
        ],
    ]))->connection();

    $connections[] = (new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'default' => [
            'host' => $host,
            'port' => $port,
            'database' => 7,
            'options' => ['serializer' => Redis::SERIALIZER_JSON],
            'timeout' => 0.5,
        ],
    ]))->connection();

    $connections[] = (new RedisManager(new Application, 'phpredis', [
        'cluster' => false,
        'default' => [
            'host' => $host,
            'port' => $port,
            'database' => 8,
            'options' => ['scan' => Redis::SCAN_RETRY],
            'timeout' => 0.5,
        ],
    ]))->connection();

    if (defined('Redis::COMPRESSION_LZF')) {
        $connections['compression_lzf'] = (new RedisManager(new Application, 'phpredis', [
            'cluster' => false,
            'default' => [
                'host' => $host,
                'port' => $port,
                'database' => 9,
                'options' => [
                    'compression' => Redis::COMPRESSION_LZF,
                    'name' => 'compression_lzf',
                ],
                'timeout' => 0.5,
            ],
        ]))->connection();
    }

    if (defined('Redis::COMPRESSION_ZSTD')) {
        $connections['compression_zstd'] = (new RedisManager(new Application, 'phpredis', [
            'cluster' => false,
            'default' => [
                'host' => $host,
                'port' => $port,
                'database' => 10,
                'options' => [
                    'compression' => Redis::COMPRESSION_ZSTD,
                    'name' => 'compression_zstd',
                ],
                'timeout' => 0.5,
            ],
        ]))->connection();

        $connections['compression_zstd_default'] = (new RedisManager(new Application, 'phpredis', [
            'cluster' => false,
            'default' => [
                'host' => $host,
                'port' => $port,
                'database' => 11,
                'options' => [
                    'compression' => Redis::COMPRESSION_ZSTD,
                    'compression_level' => Redis::COMPRESSION_ZSTD_DEFAULT,
                    'name' => 'compression_zstd_default',
                ],
                'timeout' => 0.5,
            ],
        ]))->connection();

        $connections['compression_zstd_max'] = (new RedisManager(new Application, 'phpredis', [
            'cluster' => false,
            'default' => [
                'host' => $host,
                'port' => $port,
                'database' => 13,
                'options' => [
                    'compression' => Redis::COMPRESSION_ZSTD,
                    'compression_level' => Redis::COMPRESSION_ZSTD_MAX,
                    'name' => 'compression_zstd_max',
                ],
                'timeout' => 0.5,
            ],
        ]))->connection();
    }

    if (defined('Redis::COMPRESSION_LZ4')) {
        $connections['compression_lz4'] = (new RedisManager(new Application, 'phpredis', [
            'cluster' => false,
            'default' => [
                'host' => $host,
                'port' => $port,
                'database' => 14,
                'options' => [
                    'compression' => Redis::COMPRESSION_LZ4,
                    'name' => 'compression_lz4',
                ],
                'timeout' => 0.5,
            ],
        ]))->connection();

        $connections['compression_lz4_default'] = (new RedisManager(new Application, 'phpredis', [
            'cluster' => false,
            'default' => [
                'host' => $host,
                'port' => $port,
                'database' => 15,
                'options' => [
                    'compression' => Redis::COMPRESSION_LZ4,
                    'compression_level' => 0,
                    'name' => 'compression_lz4_default',
                ],
                'timeout' => 0.5,
            ],
        ]))->connection();

        $connections['compression_lz4_min'] = (new RedisManager(new Application, 'phpredis', [
            'cluster' => false,
            'default' => [
                'host' => $host,
                'port' => $port,
                'database' => 16,
                'options' => [
                    'compression' => Redis::COMPRESSION_LZ4,
                    'compression_level' => 1,
                    'name' => 'compression_lz4_min',
                ],
                'timeout' => 0.5,
            ],
        ]))->connection();

        $connections['compression_lz4_max'] = (new RedisManager(new Application, 'phpredis', [
            'cluster' => false,
            'default' => [
                'host' => $host,
                'port' => $port,
                'database' => 17,
                'options' => [
                    'compression' => Redis::COMPRESSION_LZ4,
                    'compression_level' => 12,
                    'name' => 'compression_lz4_max',
                ],
                'timeout' => 0.5,
            ],
        ]))->connection();
    }

    return $connections;
}

/** The configured key prefix for a Predis or PhpRedis client. */
function redisPrefix($client)
{
    if ($client instanceof Redis) {
        return $client->getOption(Redis::OPT_PREFIX);
    }

    return $client->getOptions()->prefix;
}

test('it sets values with expiry', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->set('one', 'mohamed', 'EX', 5, 'NX');
        expect($redis->get('one'))->toBe('mohamed');
        expect($redis->ttl('one'))->not->toEqual(-1);

        // It doesn't override when NX mode
        $redis->set('one', 'taylor', 'EX', 5, 'NX');
        expect($redis->get('one'))->toBe('mohamed');

        // It overrides when XX mode
        $redis->set('one', 'taylor', 'EX', 5, 'XX');
        expect($redis->get('one'))->toBe('taylor');

        // It fails if XX mode is on and key doesn't exist
        $redis->set('two', 'taylor', 'PX', 5, 'XX');
        expect($redis->get('two'))->toBeNull();

        $redis->set('three', 'mohamed', 'PX', 5000);
        expect($redis->get('three'))->toBe('mohamed');
        expect($redis->ttl('three'))->not->toEqual(-1);
        expect($redis->pttl('three'))->not->toEqual(-1);

        $redis->flushall();
    }
});

test('it deletes keys', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->set('one', 'mohamed');
        $redis->set('two', 'mohamed');
        $redis->set('three', 'mohamed');

        $redis->del('one');
        expect($redis->get('one'))->toBeNull();
        expect($redis->get('two'))->not->toBeNull();
        expect($redis->get('three'))->not->toBeNull();

        $redis->del('two', 'three');
        expect($redis->get('two'))->toBeNull();
        expect($redis->get('three'))->toBeNull();

        $redis->flushall();
    }
});

test('it checks for existence', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->set('one', 'mohamed');
        $redis->set('two', 'mohamed');

        expect($redis->exists('one'))->toEqual(1);
        expect($redis->exists('nothing'))->toEqual(0);
        expect($redis->exists('one', 'two'))->toEqual(2);
        expect($redis->exists('one', 'two', 'nothing'))->toEqual(2);

        $redis->flushall();
    }
});

test('it expires keys', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->set('one', 'mohamed');
        expect($redis->ttl('one'))->toEqual(-1);
        expect($redis->expire('one', 10))->toEqual(1);
        expect($redis->ttl('one'))->not->toEqual(-1);

        expect($redis->expire('nothing', 10))->toEqual(0);

        $redis->set('two', 'mohamed');
        expect($redis->ttl('two'))->toEqual(-1);
        expect($redis->pexpire('two', 10))->toEqual(1);
        expect($redis->pttl('two'))->not->toEqual(-1);

        expect($redis->pexpire('nothing', 10))->toEqual(0);

        $redis->flushall();
    }
});

test('it renames keys', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->set('one', 'mohamed');
        $redis->rename('one', 'two');
        expect($redis->get('one'))->toBeNull();
        expect($redis->get('two'))->toBe('mohamed');

        $redis->set('three', 'adam');
        $redis->renamenx('two', 'three');
        expect($redis->get('two'))->toBe('mohamed');
        expect($redis->get('three'))->toBe('adam');

        $redis->renamenx('two', 'four');
        expect($redis->get('two'))->toBeNull();
        expect($redis->get('four'))->toBe('mohamed');
        expect($redis->get('three'))->toBe('adam');

        $redis->flushall();
    }
});

test('it adds members to sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', 1, 'mohamed');
        expect($redis->zcard('set'))->toEqual(1);

        $redis->zadd('set', 2, 'taylor', 3, 'adam');
        expect($redis->zcard('set'))->toEqual(3);

        $redis->zadd('set', ['jeffrey' => 4, 'matt' => 5]);
        expect($redis->zcard('set'))->toEqual(5);

        $redis->zadd('set', 'NX', 1, 'beric');
        expect($redis->zcard('set'))->toEqual(6);

        $redis->zadd('set', 'NX', ['joffrey' => 1]);
        expect($redis->zcard('set'))->toEqual(7);

        $redis->zadd('set', 'XX', ['ned' => 1]);
        expect($redis->zcard('set'))->toEqual(7);

        expect($redis->zadd('set', ['sansa' => 10]))->toEqual(1);
        expect($redis->zadd('set', 'XX', 'CH', ['arya' => 11]))->toEqual(0);

        $redis->zadd('set', ['mohamed' => 100]);
        expect($redis->zscore('set', 'mohamed'))->toEqual(100);

        $redis->flushall();
    }
});

test('it counts members in sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 10]);

        expect($redis->zcount('set', 1, 5))->toEqual(1);
        expect($redis->zcount('set', '-inf', '+inf'))->toEqual(2);
        expect($redis->zcard('set'))->toEqual(2);

        $redis->flushall();
    }
});

test('it increments score of sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 10]);
        $redis->zincrby('set', 2, 'jeffrey');
        expect($redis->zscore('set', 'jeffrey'))->toEqual(3);

        $redis->flushall();
    }
});

test('it sets key if not exists', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->set('name', 'mohamed');

        expect($redis->setnx('name', 'taylor'))->toBe(0);
        expect($redis->get('name'))->toBe('mohamed');

        expect($redis->setnx('boss', 'taylor'))->toBe(1);
        expect($redis->get('boss'))->toBe('taylor');

        $redis->flushall();
    }
});

test('it sets hash field if not exists', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->hset('person', 'name', 'mohamed');

        expect($redis->hsetnx('person', 'name', 'taylor'))->toBe(0);
        expect($redis->hget('person', 'name'))->toBe('mohamed');

        expect($redis->hsetnx('person', 'boss', 'taylor'))->toBe(1);
        expect($redis->hget('person', 'boss'))->toBe('taylor');

        $redis->flushall();
    }
});

test('it calculates intersection of sorted sets and stores', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set1', ['jeffrey' => 1, 'matt' => 2, 'taylor' => 3]);
        $redis->zadd('set2', ['jeffrey' => 2, 'matt' => 3]);

        $redis->zinterstore('output', ['set1', 'set2']);
        expect($redis->zcard('output'))->toEqual(2);
        expect($redis->zscore('output', 'jeffrey'))->toEqual(3);
        expect($redis->zscore('output', 'matt'))->toEqual(5);

        $redis->zinterstore('output2', ['set1', 'set2'], [
            'weights' => [3, 2],
            'aggregate' => 'sum',
        ]);
        expect($redis->zscore('output2', 'jeffrey'))->toEqual(7);
        expect($redis->zscore('output2', 'matt'))->toEqual(12);

        $redis->zinterstore('output3', ['set1', 'set2'], [
            'weights' => [3, 2],
            'aggregate' => 'min',
        ]);
        expect($redis->zscore('output3', 'jeffrey'))->toEqual(3);
        expect($redis->zscore('output3', 'matt'))->toEqual(6);

        $redis->flushall();
    }
});

test('it calculates union of sorted sets and stores', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set1', ['jeffrey' => 1, 'matt' => 2, 'taylor' => 3]);
        $redis->zadd('set2', ['jeffrey' => 2, 'matt' => 3]);

        $redis->zunionstore('output', ['set1', 'set2']);
        expect($redis->zcard('output'))->toEqual(3);
        expect($redis->zscore('output', 'jeffrey'))->toEqual(3);
        expect($redis->zscore('output', 'matt'))->toEqual(5);
        expect($redis->zscore('output', 'taylor'))->toEqual(3);

        $redis->zunionstore('output2', ['set1', 'set2'], [
            'weights' => [3, 2],
            'aggregate' => 'sum',
        ]);
        expect($redis->zscore('output2', 'jeffrey'))->toEqual(7);
        expect($redis->zscore('output2', 'matt'))->toEqual(12);
        expect($redis->zscore('output2', 'taylor'))->toEqual(9);

        $redis->zunionstore('output3', ['set1', 'set2'], [
            'weights' => [3, 2],
            'aggregate' => 'min',
        ]);
        expect($redis->zscore('output3', 'jeffrey'))->toEqual(3);
        expect($redis->zscore('output3', 'matt'))->toEqual(6);
        expect($redis->zscore('output3', 'taylor'))->toEqual(9);

        $redis->flushall();
    }
});

test('it returns range in sorted set', function () {
    foreach (redisConnections($this->redis) as $connector => $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 5, 'taylor' => 10]);
        expect($redis->zrange('set', 0, 1))->toEqual(['jeffrey', 'matt']);
        expect($redis->zrange('set', 0, -1))->toEqual(['jeffrey', 'matt', 'taylor']);

        if ($connector === 'predis') {
            expect($redis->zrange('set', 0, 1, 'withscores'))->toEqual(['jeffrey' => 1, 'matt' => 5]);
        } else {
            expect($redis->zrange('set', 0, 1, true))->toEqual(['jeffrey' => 1, 'matt' => 5]);
        }

        $redis->flushall();
    }
});

test('it returns rev range in sorted set', function () {
    foreach (redisConnections($this->redis) as $connector => $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 5, 'taylor' => 10]);
        expect($redis->ZREVRANGE('set', 0, 1))->toEqual(['taylor', 'matt']);
        expect($redis->ZREVRANGE('set', 0, -1))->toEqual(['taylor', 'matt', 'jeffrey']);

        if ($connector === 'predis') {
            expect($redis->ZREVRANGE('set', 0, 1, 'withscores'))->toEqual(['matt' => 5, 'taylor' => 10]);
        } else {
            expect($redis->ZREVRANGE('set', 0, 1, true))->toEqual(['matt' => 5, 'taylor' => 10]);
        }

        $redis->flushall();
    }
});

test('it returns range by score in sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 5, 'taylor' => 10]);
        expect($redis->zrangebyscore('set', 0, 3))->toEqual(['jeffrey']);
        expect($redis->zrangebyscore('set', 0, 11, [
            'withscores' => true,
            'limit' => [
                'offset' => 1,
                'count' => 2,
            ],
        ]))->toEqual(['matt' => 5, 'taylor' => 10]);
        expect($redis->zrangebyscore('set', 0, 11, [
            'withscores' => true,
            'limit' => [1, 2],
        ]))->toEqual(['matt' => 5, 'taylor' => 10]);

        $redis->flushall();
    }
});

test('it returns rev range by score in sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 5, 'taylor' => 10]);
        expect($redis->ZREVRANGEBYSCORE('set', 10, 6))->toEqual(['taylor']);
        expect($redis->ZREVRANGEBYSCORE('set', 10, 0, [
            'withscores' => true,
            'limit' => [
                'offset' => 1,
                'count' => 2,
            ],
        ]))->toEqual(['matt' => 5, 'jeffrey' => 1]);
        expect($redis->ZREVRANGEBYSCORE('set', 10, 0, [
            'withscores' => true,
            'limit' => [1, 2],
        ]))->toEqual(['matt' => 5, 'jeffrey' => 1]);

        $redis->flushall();
    }
});

test('it returns rank in sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 5, 'taylor' => 10]);

        expect($redis->zrank('set', 'jeffrey'))->toEqual(0);
        expect($redis->zrank('set', 'taylor'))->toEqual(2);

        $redis->flushall();
    }
});

test('it returns score in sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 5, 'taylor' => 10]);

        expect($redis->zscore('set', 'jeffrey'))->toEqual(1);
        expect($redis->zscore('set', 'taylor'))->toEqual(10);

        $redis->flushall();
    }
});

test('it removes members in sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 5, 'taylor' => 10, 'adam' => 11]);

        $redis->zrem('set', 'jeffrey');
        expect($redis->zcard('set'))->toEqual(3);

        $redis->zrem('set', 'matt', 'adam');
        expect($redis->zcard('set'))->toEqual(1);

        $redis->flushall();
    }
});

test('it removes members by score in sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 5, 'taylor' => 10, 'adam' => 11]);
        $redis->ZREMRANGEBYSCORE('set', 5, '+inf');
        expect($redis->zcard('set'))->toEqual(1);

        $redis->flushall();
    }
});

test('it removes members by rank in sorted set', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->zadd('set', ['jeffrey' => 1, 'matt' => 5, 'taylor' => 10, 'adam' => 11]);
        $redis->ZREMRANGEBYRANK('set', 1, -1);
        expect($redis->zcard('set'))->toEqual(1);

        $redis->flushall();
    }
});

test('it sets multiple hash fields', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->hmset('hash', ['name' => 'mohamed', 'hobby' => 'diving']);
        expect($redis->hgetall('hash'))->toEqual(['name' => 'mohamed', 'hobby' => 'diving']);

        $redis->hmset('hash2', 'name', 'mohamed', 'hobby', 'diving');
        expect($redis->hgetall('hash2'))->toEqual(['name' => 'mohamed', 'hobby' => 'diving']);

        $redis->flushall();
    }
});

test('it gets multiple hash fields', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->hmset('hash', ['name' => 'mohamed', 'hobby' => 'diving']);

        expect($redis->hmget('hash', 'name', 'hobby'))->toEqual(['mohamed', 'diving']);

        expect($redis->hmget('hash', ['name', 'hobby']))->toEqual(['mohamed', 'diving']);

        $redis->flushall();
    }
});

test('it gets multiple keys', function () {
    $valueSet = ['name' => 'mohamed', 'hobby' => 'diving'];

    foreach (redisConnections($this->redis) as $redis) {
        $redis->mset($valueSet);

        expect($redis->mget(array_keys($valueSet)))->toEqual(array_values($valueSet));

        $redis->flushall();
    }
});

test('it flushes', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->set('name', 'Till');
        expect($redis->exists('name'))->toBe(1);

        $redis->flushdb();
        expect($redis->exists('name'))->toBe(0);
    }
});

test('it flushes asynchronous', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->set('name', 'Till');
        expect($redis->exists('name'))->toBe(1);

        $redis->flushdb('ASYNC');
        expect($redis->exists('name'))->toBe(0);
    }
});

test('it runs eval', function () {
    foreach (redisConnections($this->redis) as $redis) {
        if ($redis instanceof PhpRedisConnection) {
            // User must decide what needs to be serialized and compressed.
            $redis->eval('redis.call("set", KEYS[1], ARGV[1])', 1, 'name', ...$redis->pack(['mohamed']));
        } else {
            $redis->eval('redis.call("set", KEYS[1], ARGV[1])', 1, 'name', 'mohamed');
        }

        expect($redis->get('name'))->toBe('mohamed');

        $redis->flushall();
    }
});

test('it runs pipes', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $result = $redis->pipeline(function ($pipe) {
            $pipe->set('test:pipeline:1', 1);
            $pipe->get('test:pipeline:1');
            $pipe->set('test:pipeline:2', 2);
            $pipe->get('test:pipeline:2');
        });

        expect($result)->toHaveCount(4);
        expect($result[1])->toEqual(1);
        expect($result[3])->toEqual(2);

        $redis->flushall();
    }
});

test('it runs transactions', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $result = $redis->transaction(function ($pipe) {
            $pipe->set('test:transaction:1', 1);
            $pipe->get('test:transaction:1');
            $pipe->set('test:transaction:2', 2);
            $pipe->get('test:transaction:2');
        });

        expect($result)->toHaveCount(4);
        expect($result[1])->toEqual(1);
        expect($result[3])->toEqual(2);

        $redis->flushall();
    }
});

test('it runs raw command', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->executeRaw(['SET', 'test:raw:1', '1']);

        expect($redis->executeRaw(['GET', 'test:raw:1']))->toEqual(1);

        $redis->flushall();
    }
});

test('it dispatches query event', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $redis->setEventDispatcher($events = m::mock(Dispatcher::class));

        $events->shouldReceive('dispatch')->once()->with(m::on(function ($event) {
            expect($event->command)->toBe('get');
            expect($event->parameters)->toEqual(['foobar']);
            expect($event->connectionName)->toBe('default');
            expect($event->connection)->toBeInstanceOf(Connection::class);

            return true;
        }));

        $redis->get('foobar');

        $redis->unsetEventDispatcher();
    }
});

test('it persists connection', function () {
    if (PHP_ZTS) {
        $this->markTestSkipped('PhpRedis does not support persistent connections with PHP_ZTS enabled.');
    }

    expect(redisConnections($this->redis)['persistent']->getPersistentID())->toBe('laravel');
});

test('it scans for keys', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $initialKeys = ['test:scan:1', 'test:scan:2'];

        foreach ($initialKeys as $k => $key) {
            $redis->set($key, 'test');
            $initialKeys[$k] = redisPrefix($redis->client()).$key;
        }

        $iterator = null;

        do {
            [$cursor, $returnedKeys] = $redis->scan($iterator);

            if (! is_array($returnedKeys)) {
                $returnedKeys = [$returnedKeys];
            }

            foreach ($returnedKeys as $returnedKey) {
                expect($initialKeys)->toContain($returnedKey);
            }
        } while ($iterator > 0);

        $redis->flushAll();
    }
});

test('it zscans for keys', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $members = [100 => 'test:zscan:1', 200 => 'test:zscan:2'];

        foreach ($members as $score => $member) {
            $redis->zadd('set', $score, $member);
        }

        $iterator = null;
        $result = [];

        do {
            [$iterator, $returnedMembers] = $redis->zscan('set', $iterator);

            if (! is_array($returnedMembers)) {
                $returnedMembers = [$returnedMembers];
            }

            foreach ($returnedMembers as $member => $score) {
                expect($members)->toHaveKey((int) $score);
                expect($members)->toContain($member);
            }

            $result += $returnedMembers;
        } while ($iterator > 0);

        expect($result)->toHaveCount(2);

        $iterator = null;
        [$iterator, $returned] = $redis->zscan('set', $iterator, ['match' => 'test:unmatch:*']);
        expect($returned)->toBeEmpty();

        $iterator = null;
        [$iterator, $returned] = $redis->zscan('set', $iterator, ['count' => 5]);
        expect($returned)->toHaveCount(2);

        $redis->flushAll();
    }
});

test('it hscans for keys', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $fields = ['name' => 'mohamed', 'hobby' => 'diving'];

        foreach ($fields as $field => $value) {
            $redis->hset('hash', $field, $value);
        }

        $iterator = null;
        $result = [];

        do {
            [$iterator, $returnedFields] = $redis->hscan('hash', $iterator);

            foreach ($returnedFields as $field => $value) {
                expect($fields)->toHaveKey($field);
                expect($fields)->toContain($value);
            }

            $result += $returnedFields;
        } while ($iterator > 0);

        expect($result)->toHaveCount(2);

        $iterator = null;
        [$iterator, $returned] = $redis->hscan('hash', $iterator, ['match' => 'test:unmatch:*']);
        expect($returned)->toBeEmpty();

        $iterator = null;
        [$iterator, $returned] = $redis->hscan('hash', $iterator, ['count' => 5]);
        expect($returned)->toHaveCount(2);

        $redis->flushAll();
    }
});

test('it sscans for keys', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $members = ['test:sscan:1', 'test:sscan:2'];

        foreach ($members as $member) {
            $redis->sadd('set', $member);
        }

        $iterator = null;
        $result = [];

        do {
            [$iterator, $returnedMembers] = $redis->sscan('set', $iterator);

            foreach ($returnedMembers as $member) {
                expect($members)->toContain($member);
                $result[] = $member;
            }
        } while ($iterator > 0);

        expect($result)->toHaveCount(2);

        $iterator = null;
        [$iterator, $returned] = $redis->sscan('set', $iterator, ['match' => 'test:unmatch:*']);
        expect($returned)->toBeEmpty();

        $iterator = null;
        [$iterator, $returned] = $redis->sscan('set', $iterator, ['count' => 5]);
        expect($returned)->toHaveCount(2);

        $redis->flushAll();
    }
});

test('it s pops for keys', function () {
    foreach (redisConnections($this->redis) as $redis) {
        $members = ['test:spop:1', 'test:spop:2', 'test:spop:3', 'test:spop:4'];

        foreach ($members as $member) {
            $redis->sadd('set', $member);
        }

        $result = $redis->spop('set');
        expect($result)->not->toBeArray();
        expect($members)->toContain($result);

        $result = $redis->spop('set', 1);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);

        $result = $redis->spop('set', 2);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(2);

        $redis->flushAll();
    }
});

test('php redis scan option', function () {
    foreach (redisConnections($this->redis) as $redis) {
        if ($redis->client() instanceof Client) {
            continue;
        }

        $iterator = null;

        do {
            $returned = $redis->scan($iterator);

            if ($redis->client()->getOption(Redis::OPT_SCAN) === Redis::SCAN_RETRY) {
                expect($returned)->toBeEmpty();
            }
        } while ($iterator > 0);
    }
});

test('macroable', function () {
    Connection::macro('foo', function () {
        return 'foo';
    });

    foreach (redisConnections($this->redis) as $redis) {
        expect($redis->foo())->toBe('foo');
    }
});
