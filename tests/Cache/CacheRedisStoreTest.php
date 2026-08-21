<?php

use Voyager\Cache\RedisStore;
use Voyager\Contracts\Redis\Factory;

/** A RedisStore wrapping a mocked Redis factory, prefixed the way the suite expects. */
function redisStoreWithMockFactory(): RedisStore
{
    return new RedisStore(Mockery::mock(Factory::class), 'prefix:');
}

test('get returns null when not found', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('get')->once()->with('prefix:foo')->andReturn(null);

    expect($redis->get('foo'))->toBeNull();
});

test('the redis value is returned', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('get')->once()->with('prefix:foo')->andReturn(serialize('foo'));

    expect($redis->get('foo'))->toBe('foo');
});

test('multiple redis values are returned', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('mget')->once()->with(['prefix:foo', 'prefix:fizz', 'prefix:norf', 'prefix:null'])
        ->andReturn([
            serialize('bar'),
            serialize('buzz'),
            serialize('quz'),
            null,
        ]);

    $results = $redis->many(['foo', 'fizz', 'norf', 'null']);

    expect($results['foo'])->toBe('bar')
        ->and($results['fizz'])->toBe('buzz')
        ->and($results['norf'])->toBe('quz')
        ->and($results['null'])->toBeNull();
});

test('the redis value is returned for numerics', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('get')->once()->with('prefix:foo')->andReturn(1);

    expect($redis->get('foo'))->toEqual(1);
});

test('the set method properly calls redis', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('setex')->once()->with('prefix:foo', 60, serialize('foo'))->andReturn('OK');
    $result = $redis->put('foo', 'foo', 60);

    expect($result)->toBeTrue();
});

test('the setMultiple method properly calls redis', function () {
    $redis = redisStoreWithMockFactory();
    /** @var \Mockery\MockInterface $connection */
    $connection = $redis->getRedis();
    $connection->shouldReceive('connection')->with('default')->andReturn($redis->getRedis());
    $connection->shouldReceive('multi')->once();
    $redis->getRedis()->shouldReceive('setex')->once()->with('prefix:foo', 60, serialize('bar'))->andReturn('OK');
    $redis->getRedis()->shouldReceive('setex')->once()->with('prefix:baz', 60, serialize('qux'))->andReturn('OK');
    $redis->getRedis()->shouldReceive('setex')->once()->with('prefix:bar', 60, serialize('norf'))->andReturn('OK');
    $connection->shouldReceive('exec')->once();

    $result = $redis->putMany([
        'foo' => 'bar',
        'baz' => 'qux',
        'bar' => 'norf',
    ], 60);

    expect($result)->toBeTrue();
});

test('the set method properly calls redis for numerics', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('setex')->once()->with('prefix:foo', 60, 1);
    $result = $redis->put('foo', 1, 60);

    expect($result)->toBeFalse();
});

test('the increment method properly calls redis', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('incrby')->once()->with('prefix:foo', 5);

    $redis->increment('foo', 5);
});

test('the decrement method properly calls redis', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('decrby')->once()->with('prefix:foo', 5);

    $redis->decrement('foo', 5);
});

test('storing an item forever properly calls redis', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('set')->once()->with('prefix:foo', serialize('foo'))->andReturn('OK');
    $result = $redis->forever('foo', 'foo', 60);

    expect($result)->toBeTrue();
});

test('the forget method properly calls redis', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('del')->once()->with('prefix:foo');

    $redis->forget('foo');
});

test('flushes the cache', function () {
    $redis = redisStoreWithMockFactory();
    $redis->getRedis()->shouldReceive('connection')->once()->with('default')->andReturn($redis->getRedis());
    $redis->getRedis()->shouldReceive('flushdb')->once()->andReturn('ok');
    $result = $redis->flush();

    expect($result)->toBeTrue();
});

test('the prefix can be gotten and set', function () {
    $redis = redisStoreWithMockFactory();
    expect($redis->getPrefix())->toBe('prefix:');

    $redis->setPrefix('foo');
    expect($redis->getPrefix())->toBe('foo');

    $redis->setPrefix(null);
    expect($redis->getPrefix())->toBeEmpty();
});
