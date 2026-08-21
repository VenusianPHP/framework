<?php

use Voyager\Cache\ArrayStore;
use Voyager\Console\Scheduling\CacheEventMutex;
use Voyager\Console\Scheduling\CacheSchedulingMutex;
use Voyager\Console\Scheduling\Event;
use Voyager\Contracts\Cache\Factory;
use Voyager\Contracts\Cache\Repository;
use Voyager\NutsAndBolts\DataObjects\Carbon;

/**
 * A scheduling mutex, the event it guards, the minute it guards it for, and the
 * cache doubles behind it.
 *
 * @return array{CacheSchedulingMutex, Event, Carbon, Mockery\MockInterface, Mockery\MockInterface}
 */
function schedulingMutexUnderTest(): array
{
    $factory = Mockery::mock(Factory::class);
    $repository = Mockery::mock(Repository::class);
    $factory->shouldReceive('store')->andReturn($repository);

    return [
        new CacheSchedulingMutex($factory),
        new Event(new CacheEventMutex($factory), 'command'),
        Carbon::now(),
        $factory,
        $repository,
    ];
}

describe('a plain cache store', function () {
    test('creating the mutex writes the key for the hour and minute', function () {
        [$mutex, $event, $time, , $repository] = schedulingMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $repository->shouldReceive('add')->once()->with($event->mutexName().$time->format('Hi'), true, 3600)->andReturn(true);

        expect($mutex->create($event, $time))->toBeTrue();
    });

    test('a custom connection is used for the write', function () {
        [$mutex, $event, $time, $factory, $repository] = schedulingMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $factory->shouldReceive('store')->with('test')->andReturn($repository);
        $repository->shouldReceive('add')->once()->with($event->mutexName().$time->format('Hi'), true, 3600)->andReturn(true);

        $mutex->useStore('test');

        expect($mutex->create($event, $time))->toBeTrue();
    });

    test('a second run in the same minute is refused', function () {
        [$mutex, $event, $time, , $repository] = schedulingMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $repository->shouldReceive('add')->once()->with($event->mutexName().$time->format('Hi'), true, 3600)->andReturn(false);

        expect($mutex->create($event, $time))->toBeFalse();
    });

    test('exists is false for a schedule that has not run', function () {
        [$mutex, $event, $time, , $repository] = schedulingMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $repository->shouldReceive('has')->once()->with($event->mutexName().$time->format('Hi'))->andReturn(false);

        expect($mutex->exists($event, $time))->toBeFalse();
    });

    test('exists is true for a schedule that has run', function () {
        [$mutex, $event, $time, , $repository] = schedulingMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new stdClass);
        $repository->shouldReceive('has')->with($event->mutexName().$time->format('Hi'))->andReturn(true);

        expect($mutex->exists($event, $time))->toBeTrue();
    });
});

describe('a lock provider store', function () {
    test('creating the mutex takes the lock', function () {
        [$mutex, $event, $time, , $repository] = schedulingMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new ArrayStore);

        expect($mutex->create($event, $time))->toBeTrue();
    });

    test('a second run in the same minute is refused', function () {
        [$mutex, $event, $time, , $repository] = schedulingMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new ArrayStore);

        // first create the lock, so we can test that the next call fails.
        $mutex->create($event, $time);

        expect($mutex->create($event, $time))->toBeFalse();
    });

    test('exists is false for a schedule that has not run', function () {
        [$mutex, $event, $time, , $repository] = schedulingMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new ArrayStore);

        expect($mutex->exists($event, $time))->toBeFalse();
    });

    test('exists is true for a schedule that has run', function () {
        [$mutex, $event, $time, , $repository] = schedulingMutexUnderTest();

        $repository->shouldReceive('getStore')->andReturn(new ArrayStore);

        $mutex->create($event, $time);

        expect($mutex->exists($event, $time))->toBeTrue();
    });
});
