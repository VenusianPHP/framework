<?php

use Voyager\Cache\Async\FileOperation;
use Voyager\Cache\Async\FileOperationKind;
use Voyager\Cache\Signals\CacheHit;
use Voyager\Cache\Signals\KeyWritten;
use Voyager\Contracts\IOPools\Promise;
use Venusian\Tests\Cache\Fixtures\CacheApp;

dataset('stores', function () {
    yield 'array' => ['array'];
    yield 'database' => ['database'];
    yield 'file' => ['file'];

    if (CacheApp::redisReachable()) {
        yield 'redis' => ['redis'];
    }
});

dataset('io stores', function () {
    yield 'database' => ['database'];
    yield 'file' => ['file'];

    if (CacheApp::redisReachable()) {
        yield 'redis' => ['redis'];
    }
});

beforeEach(fn () => $this->app = CacheApp::boot());
afterEach(fn () => CacheApp::tearDown($this->app, $this));

it('answers every operation as a promise, with the blocking call\'s answers', function (string $store) {
    $async = $this->app->get('cache')->store($store)->async();

    expect($async->put('greeting', ['hello' => 'world'], 60))->toBeInstanceOf(Promise::class)
        ->and($async->put('greeting', ['hello' => 'world'], 60)->wait())->toBeTrue()
        ->and($async->get('greeting')->wait())->toBe(['hello' => 'world'])
        ->and($async->get('absent', 'fallback')->wait())->toBe('fallback')
        ->and($async->add('greeting', 'again', 60)->wait())->toBeFalse()
        ->and($async->add('fresh', 'first', 60)->wait())->toBeTrue()
        ->and($async->add('forever-fresh', 'kept')->wait())->toBeTrue()
        ->and($async->increment('counter', 5)->wait())->toBe(5)
        ->and($async->decrement('counter', 2)->wait())->toBe(3)
        ->and($async->remember('remembered', 60, fn () => 'computed')->wait())->toBe('computed')
        ->and($async->remember('remembered', 60, fn () => 'not again')->wait())->toBe('computed')
        ->and($async->pull('fresh')->wait())->toBe('first')
        ->and($async->has('fresh')->wait())->toBeFalse()
        ->and($async->forever('kept', 42)->wait())->toBeTrue()
        ->and($async->forget('greeting')->wait())->toBeTrue()
        ->and($async->forget('greeting')->wait())->toBeFalse()
        ->and($async->put('expired', 'never', 0)->wait())->toBeFalse();
})->with('stores');

it('reads what the other path wrote, both ways', function (string $store) {
    $cache = $this->app->get('cache')->store($store);
    $object = new ArrayObject(['nested' => [1, 2, 3]]);

    $cache->put('from-blocking', $object, 60);
    $cache->async()->put('from-async', ['list' => [1.5, 'two'], 'count' => 3], 60)->wait();
    $cache->async()->put('number', 12345, 60)->wait();

    expect($cache->async()->get('from-blocking')->wait())->toEqual($object)
        ->and($cache->get('from-async'))->toBe(['list' => [1.5, 'two'], 'count' => 3])
        ->and($cache->get('number'))->toEqual(12345);
})->with('io stores');

it('runs the file store\'s operations in a worker, and an array store\'s right here', function () {
    $pool = $this->app->get('process-workers');

    $this->app->get('cache')->store('array')->async()->put('here', 'inline', 60)->wait();
    expect($pool->workerCount())->toBe(0);

    $this->app->get('cache')->store('file')->async()->put('there', 'in a worker', 60)->wait();
    expect($pool->workerCount())->toBeGreaterThan(0)
        ->and($this->app->get('cache')->store('file')->get('there'))->toBe('in a worker');
});

it('completes a key\'s operations in the order they were made, however many workers there are', function (string $store) {
    $async = $this->app->get('cache')->store($store)->async();

    $writes = array_map(fn (int $n): Promise => $async->put('ordered', $n, 60), range(1, 20));
    $read = $async->get('ordered');
    $counts = array_map(fn (): Promise => $async->increment('tally'), range(1, 30));

    array_map(fn (Promise $promise) => $promise->wait(), [...$writes, ...$counts]);

    // Redis hands back a number as the string it stores it as, blocking or async.
    expect($read->wait())->toEqual(20)
        ->and($async->get('tally')->wait())->toEqual(30);
})->with('io stores');

it('stores the value as it was when put was called', function (string $store) {
    $async = $this->app->get('cache')->store($store)->async();
    $value = new ArrayObject(['state' => 'at the call']);

    $written = $async->put('snapshot', $value, 60);
    $value['state'] = 'changed after';
    $written->wait();

    expect($async->get('snapshot')->wait()['state'])->toBe('at the call');
})->with('io stores');

it('makes a blocking call on a key wait for the async operations made on it', function (string $store) {
    $cache = $this->app->get('cache')->store($store);

    $cache->async()->put('handed-over', 'async write', 60);

    expect($cache->get('handed-over'))->toBe('async write');
})->with('io stores');

it('counts the TTL from the call: a put whose time ran out while it waited stores nothing', function () {
    $directory = $this->app['config']->get('cache.stores.file.path');

    $stored = new FileOperation($directory, null, FileOperationKind::PUT, 'late', serialize('too late'), time() - 1)->handle();

    expect($stored)->toBeTrue()
        ->and($this->app->get('cache')->store('file')->get('late'))->toBeNull();
});

it('dispatches the cache signals once, here', function (string $store) {
    $signals = $this->app->get('signals');
    $heard = [];
    $signals->listen(KeyWritten::class, function (KeyWritten $written) use (&$heard) { $heard[] = "written {$written->key}"; });
    $signals->listen(CacheHit::class, function (CacheHit $hit) use (&$heard) { $heard[] = "hit {$hit->key}"; });

    $async = $this->app->get('cache')->store($store)->async();
    $async->put('signalled', 'value', 60)->wait();
    $async->get('signalled')->wait();

    expect($heard)->toBe(['written signalled', 'hit signalled']);
})->with('stores');

it('moves a large value both ways', function (string $store) {
    $cache = $this->app->get('cache')->store($store);
    $large = random_bytes(4 * 1024 * 1024);

    expect($cache->async()->put('large', $large, 60)->wait())->toBeTrue()
        ->and($cache->async()->get('large')->wait() === $large)->toBeTrue()
        ->and($cache->get('large') === $large)->toBeTrue();
})->with('io stores');

it('agrees with the blocking store when the connection has phpredis serialize and compress on', function () {
    if (! CacheApp::redisReachable()) {
        $this->markTestSkipped('needs a Redis on 127.0.0.1:6379');
    }

    $this->app['config']->set('database.redis.cache.options', [
        'serializer' => Redis::SERIALIZER_PHP,
        'compression' => defined('Redis::COMPRESSION_LZF') ? Redis::COMPRESSION_LZF : Redis::COMPRESSION_NONE,
    ]);
    $cache = $this->app->get('cache')->store('redis');

    $cache->put('packed-blocking', ['packed' => true], 60);
    $cache->async()->put('packed-async', ['packed' => 'async'], 60)->wait();

    expect($cache->async()->get('packed-blocking')->wait())->toBe(['packed' => true])
        ->and($cache->get('packed-async'))->toBe(['packed' => 'async']);
});

it('has no async operations on a tagged cache', function () {
    if (! CacheApp::redisReachable()) {
        $this->markTestSkipped('needs a Redis on 127.0.0.1:6379');
    }

    expect(fn () => $this->app->get('cache')->store('redis')->tags(['people'])->async())
        ->toThrow(BadMethodCallException::class, 'A tagged cache has no async operations: its tag sets are kept by the blocking calls only.');
});

it('sends the file store\'s operations to the thread pool when it is on', function () {
    $threads = new Venusian\Tests\Log\Fixtures\RecordingPool($this->app->get(Voyager\Contracts\IOPools\Loop::class));
    $this->app->registerInstance('thread-workers', $threads);

    $this->app->get('cache')->store('file')->async()->put('threaded', 'value', 60)->wait();

    expect($threads->gigs)->toHaveCount(1)
        ->and($threads->gigs[0])->toBeInstanceOf(FileOperation::class)
        ->and($this->app->get('process-workers')->workerCount())->toBe(0);
});
