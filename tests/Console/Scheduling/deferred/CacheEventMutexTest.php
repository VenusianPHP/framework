<?php

use Voyager\Cache\ArrayStore;
use Voyager\Console\Scheduling\CacheEventMutex;
use Voyager\Console\Scheduling\Event;
use Voyager\Contracts\Cache\Factory;
use Voyager\Contracts\Cache\Repository;

/**
 * An event mutex, the event it guards, and the cache doubles behind it.
 *
 * @return array{CacheEventMutex, Event, Mockery\MockInterface, Mockery\MockInterface}
 */
function eventMutexUnderTest(): array
{
    $factory = Mockery::mock(Factory::class);
    $repository = Mockery::mock(Repository::class);
    $factory->shouldReceive('store')->andReturn($repository);

    $mutex = new CacheEventMutex($factory);

    return [$mutex, new Event($mutex, 'command'), $factory, $repository];
}

describe('a plain cache store', function () {
    test('creating the mutex writes the key', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $repository->shouldReceive('add')->once();

        $mutex->create($event);
    });

    test('a custom connection is used for the write', function () {
        [$mutex, $event, $factory, $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $factory->shouldReceive('store')->with('test')->andReturn($repository);
        $repository->shouldReceive('add')->once();
        $mutex->useStore('test');

        $mutex->create($event);
    });

    test('creating the mutex fails when the key is taken', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $repository->shouldReceive('add')->once()->andReturn(false);

        expect($mutex->create($event))->toBeFalse();
    });

    test('exists is false for a task that is not running', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $repository->shouldReceive('has')->once()->andReturn(false);

        expect($mutex->exists($event))->toBeFalse();
    });

    test('exists is true for a task that is running', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $repository->shouldReceive('has')->once()->andReturn(true);

        expect($mutex->exists($event))->toBeTrue();
    });

    test('forget drops the key', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $repository->shouldReceive('forget')->once();

        $mutex->forget($event);
    });
});

describe('a lock provider store', function () {
    test('creating the mutex takes the lock', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new ArrayStore);

        expect($mutex->create($event))->toBeTrue();
    });

    test('creating the mutex fails while the lock is held', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new ArrayStore);

        // first create the lock, so we can test that the next call fails.
        $mutex->create($event);

        expect($mutex->create($event))->toBeFalse();
    });

    test('exists is false for a task that is not running', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new ArrayStore);

        expect($mutex->exists($event))->toBeFalse();
    });

    test('exists is true for a task that is running', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new ArrayStore);

        $mutex->create($event);

        expect($mutex->exists($event))->toBeTrue();
    });

    test('forget releases the lock', function () {
        [$mutex, $event, , $repository] = eventMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new ArrayStore);

        $mutex->create($event);

        $mutex->forget($event);

        expect($mutex->exists($event))->toBeFalse();
    });
});
