<?php

use Voyager\Cache\ArrayStore;
use Voyager\Cache\Events\CacheFlushed;
use Voyager\Cache\Events\CacheFlushFailed;
use Voyager\Cache\Events\CacheFlushing;
use Voyager\Cache\Events\CacheHit;
use Voyager\Cache\Events\CacheMissed;
use Voyager\Cache\Events\ForgettingKey;
use Voyager\Cache\Events\KeyForgetFailed;
use Voyager\Cache\Events\KeyForgotten;
use Voyager\Cache\Events\KeyWritten;
use Voyager\Cache\Events\RetrievingKey;
use Voyager\Cache\Events\RetrievingManyKeys;
use Voyager\Cache\Events\WritingKey;
use Voyager\Cache\Events\WritingManyKeys;
use Voyager\Cache\Repository;
use Voyager\Contracts\Cache\Store;
use Voyager\Events\Dispatcher;

/** A Mockery argument matcher for a dispatched event of the given class and property values. */
function eventMatching($eventClass, $properties = [])
{
    return Mockery::on(function ($event) use ($eventClass, $properties) {
        if (! $event instanceof $eventClass) {
            return false;
        }

        foreach ($properties as $name => $value) {
            if ($value != $event->$name) {
                return false;
            }
        }

        return true;
    });
}

/** A fresh Mockery dispatcher double. */
function mockDispatcher()
{
    return Mockery::mock(Dispatcher::class);
}

/** An ArrayStore-backed Repository pre-populated with a plain and a tagged key, wired to the given dispatcher. */
function repositoryWithDispatcher($dispatcher)
{
    $repository = new Repository(new ArrayStore, ['store' => 'array']);
    $repository->put('baz', 'qux', 99);
    $repository->tags('taylor')->put('baz', 'qux', 99);
    $repository->setEventDispatcher($dispatcher);

    return $repository;
}

test('has triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo']));
    expect($repository->has('foo'))->toBeFalse();

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'baz']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheHit::class, ['storeName' => 'array', 'key' => 'baz', 'value' => 'qux']));
    expect($repository->has('baz'))->toBeTrue();

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo', 'tags' => ['taylor']]));
    expect($repository->tags('taylor')->has('foo'))->toBeFalse();

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'baz', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheHit::class, ['storeName' => 'array', 'key' => 'baz', 'value' => 'qux', 'tags' => ['taylor']]));
    expect($repository->tags('taylor')->has('baz'))->toBeTrue();
});

test('get triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo']));
    expect($repository->get('foo'))->toBeNull();

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingManyKeys::class, ['storeName' => 'array', 'keys' => ['foo', 'bar']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'bar']));
    expect($repository->get(['foo', 'bar']))->toBe(['foo' => null, 'bar' => null]);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'baz']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheHit::class, ['storeName' => 'array', 'key' => 'baz', 'value' => 'qux']));
    expect($repository->get('baz'))->toBe('qux');

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo', 'tags' => ['taylor']]));
    expect($repository->tags('taylor')->get('foo'))->toBeNull();

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'baz', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheHit::class, ['storeName' => 'array', 'key' => 'baz', 'value' => 'qux', 'tags' => ['taylor']]));
    expect($repository->tags('taylor')->get('baz'))->toBe('qux');
});

test('pull triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'baz']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheHit::class, ['storeName' => 'array', 'key' => 'baz', 'value' => 'qux']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(ForgettingKey::class, ['storeName' => 'array', 'key' => 'baz']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyForgotten::class, ['storeName' => 'array', 'key' => 'baz']));

    expect($repository->pull('baz'))->toBe('qux');
});

test('pull triggers events using tags', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'baz', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheHit::class, ['storeName' => 'array', 'key' => 'baz', 'value' => 'qux', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(ForgettingKey::class, ['storeName' => 'array', 'key' => 'baz', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyForgotten::class, ['storeName' => 'array', 'key' => 'baz', 'tags' => ['taylor']]));

    expect($repository->tags('taylor')->pull('baz'))->toBe('qux');
});

test('put triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99]));
    $repository->put('foo', 'bar', 99);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingManyKeys::class, ['storeName' => 'array', 'keys' => ['foo', 'baz'], 'values' => ['bar', 'qux'], 'seconds' => 99]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'baz', 'value' => 'qux', 'seconds' => 99]));
    $repository->putMany(['foo' => 'bar', 'baz' => 'qux'], 99);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99, 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99, 'tags' => ['taylor']]));
    $repository->tags('taylor')->put('foo', 'bar', 99);
});

test('add triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99]));
    expect($repository->add('foo', 'bar', 99))->toBeTrue();

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99, 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99, 'tags' => ['taylor']]));
    expect($repository->tags('taylor')->add('foo', 'bar', 99))->toBeTrue();
});

test('forever triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => null]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => null]));
    $repository->forever('foo', 'bar');

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => null, 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => null, 'tags' => ['taylor']]));
    $repository->tags('taylor')->forever('foo', 'bar');
});

test('remember triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99]));
    expect($repository->remember('foo', 99, function () {
        return 'bar';
    }))->toBe('bar');

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99, 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => 99, 'tags' => ['taylor']]));
    expect($repository->tags('taylor')->remember('foo', 99, function () {
        return 'bar';
    }))->toBe('bar');
});

test('rememberForever triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => null]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => null]));
    expect($repository->rememberForever('foo', function () {
        return 'bar';
    }))->toBe('bar');

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(RetrievingKey::class, ['storeName' => 'array', 'key' => 'foo']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(CacheMissed::class, ['storeName' => 'array', 'key' => 'foo', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(WritingKey::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => null, 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyWritten::class, ['storeName' => 'array', 'key' => 'foo', 'value' => 'bar', 'seconds' => null, 'tags' => ['taylor']]));
    expect($repository->tags('taylor')->rememberForever('foo', function () {
        return 'bar';
    }))->toBe('bar');
});

test('forget triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(ForgettingKey::class, ['storeName' => 'array', 'key' => 'baz']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyForgotten::class, ['storeName' => 'array', 'key' => 'baz']));
    expect($repository->forget('baz'))->toBeTrue();

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(ForgettingKey::class, ['storeName' => 'array', 'key' => 'baz', 'tags' => ['taylor']]));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyForgotten::class, ['storeName' => 'array', 'key' => 'baz', 'tags' => ['taylor']]));
    expect($repository->tags('taylor')->forget('baz'))->toBeTrue();
});

test('forget triggers a failed event on failure', function () {
    $dispatcher = mockDispatcher();
    $store = Mockery::mock(Store::class);
    $store->shouldReceive('forget')->andReturn(false);
    $repository = new Repository($store);
    $repository->setEventDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(ForgettingKey::class, ['key' => 'baz']));
    $dispatcher->shouldReceive('dispatch')->once()->with(eventMatching(KeyForgetFailed::class, ['key' => 'baz']));

    expect($repository->forget('baz'))->toBeFalse();
});

test('flush triggers events', function () {
    $dispatcher = mockDispatcher();
    $repository = repositoryWithDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(
        eventMatching(CacheFlushing::class, [
            'storeName' => 'array',
        ])
    );

    $dispatcher->shouldReceive('dispatch')->once()->with(
        eventMatching(CacheFlushed::class, [
            'storeName' => 'array',
        ])
    );

    expect($repository->clear())->toBeTrue();
});

test('a flush failure does dispatch an event', function () {
    $dispatcher = mockDispatcher();

    // Create a store that fails to flush
    $failingStore = Mockery::mock(Store::class);
    $failingStore->shouldReceive('flush')->andReturn(false);

    $repository = new Repository($failingStore, ['store' => 'array']);
    $repository->setEventDispatcher($dispatcher);

    $dispatcher->shouldReceive('dispatch')->once()->with(
        eventMatching(CacheFlushing::class, [
            'storeName' => 'array',
        ])
    );

    $dispatcher->shouldReceive('dispatch')->once()->with(
        eventMatching(CacheFlushFailed::class, [
            'storeName' => 'array',
        ])
    );

    expect($repository->clear())->toBeFalse();
});
