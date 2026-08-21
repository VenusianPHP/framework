<?php

use Mockery\MockInterface;
use Tests\Console\Fixtures\IsolatableNamedCommand;
use Tests\Console\Fixtures\NamedCommand;
use Voyager\Console\CacheCommandMutex;
use Voyager\Contracts\Cache\Factory;
use Voyager\Contracts\Cache\LockProvider;
use Voyager\Contracts\Cache\Repository;

/**
 * A fresh mutex together with the cache factory and repository doubles it
 * resolves through.
 *
 * @return array{CacheCommandMutex, MockInterface, MockInterface}
 */
function cacheMutexUnderTest(): array
{
    $factory = Mockery::mock(Factory::class);
    $repository = Mockery::mock(Repository::class);

    return [new CacheCommandMutex($factory), $factory, $repository];
}

/** Expect the factory to hand back a store that is not a lock provider. */
function expectCacheMutexUsesStore(MockInterface $factory, MockInterface $repository): void
{
    $factory->expects('store')->once()->andReturn($repository);
    $repository->expects('getStore')->andReturn(null);
}

/** Expect the factory to hand back a store that is a lock provider. */
function expectCacheMutexUsesLockProvider(MockInterface $factory, MockInterface $repository): MockInterface
{
    $lock = Mockery::mock(LockProvider::class);

    $factory->expects('store')->once()->andReturn($repository);
    $repository->expects('getStore')->twice()->andReturn($lock);

    return $lock;
}

/** Expect a lock to be taken out, granted or refused. */
function expectCacheMutexAcquiresLock(MockInterface $lock, bool $acquiresSuccessfully): void
{
    $lock->expects('lock')
        ->once()
        ->with(Mockery::type('string'), Mockery::type('int'))
        ->andReturns($lock);

    $lock->expects('get')
        ->once()
        ->andReturns($acquiresSuccessfully);
}

describe('a cache store', function () {
    test('creating the mutex succeeds when the key is free', function () {
        [$mutex, $factory, $repository] = cacheMutexUnderTest();

        expectCacheMutexUsesStore($factory, $repository);
        $repository->shouldReceive('add')
            ->andReturn(true)
            ->once();

        $actual = $mutex->create(new NamedCommand);

        expect($actual)->toBeTrue();
    });

    test('creating the mutex fails when the key already exists', function () {
        [$mutex, $factory, $repository] = cacheMutexUnderTest();

        expectCacheMutexUsesStore($factory, $repository);
        $repository->shouldReceive('add')
            ->andReturn(false)
            ->once();

        $actual = $mutex->create(new NamedCommand);

        expect($actual)->toBeFalse();
    });

    test('the mutex can be pointed at a named store', function () {
        [$mutex, $factory, $repository] = cacheMutexUnderTest();

        expectCacheMutexUsesStore($factory, $repository);
        $repository->shouldReceive('getStore')
            ->with('test')
            ->andReturn($repository);
        $repository->shouldReceive('add')
            ->andReturn(false)
            ->once();

        $mutex->useStore('test');

        $mutex->create(new NamedCommand);
    });
});

describe('a lock provider', function () {
    test('creating the mutex succeeds when the lock is granted', function () {
        [$mutex, $factory, $repository] = cacheMutexUnderTest();

        $lock = expectCacheMutexUsesLockProvider($factory, $repository);
        expectCacheMutexAcquiresLock($lock, true);

        $actual = $mutex->create(new NamedCommand);

        expect($actual)->toBeTrue();
    });

    test('the mutex can be pointed at a named lock provider store', function () {
        [$mutex, $factory, $repository] = cacheMutexUnderTest();

        expectCacheMutexUsesStore($factory, $repository);
        $repository->shouldReceive('getStore')
            ->with('test')
            ->andReturn($repository);
        $repository->shouldReceive('add')
            ->andReturn(false)
            ->once();

        $mutex->useStore('test');

        $mutex->create(new NamedCommand);
    });

    test('creating the mutex fails when the lock is refused', function () {
        [$mutex, $factory, $repository] = cacheMutexUnderTest();

        $lock = expectCacheMutexUsesLockProvider($factory, $repository);
        expectCacheMutexAcquiresLock($lock, false);

        $actual = $mutex->create(new NamedCommand);

        expect($actual)->toBeFalse();
    });

    test('a named connection resolves the lock provider from that store', function () {
        [$mutex, $factory, $repository] = cacheMutexUnderTest();

        $lock = Mockery::mock(LockProvider::class);
        $factory->expects('store')->once()->with('test')->andReturn($repository);
        $repository->expects('getStore')->twice()->andReturn($lock);

        expectCacheMutexAcquiresLock($lock, true);
        $mutex->useStore('test');

        $mutex->create(new NamedCommand);
    });
});

describe('the mutex key', function () {
    test('a plain command is keyed by its name', function () {
        [$mutex, $factory, $repository] = cacheMutexUnderTest();

        expectCacheMutexUsesStore($factory, $repository);

        $repository->shouldReceive('getStore')
            ->with('test')
            ->andReturn($repository);

        $repository->shouldReceive('add')
            ->once()
            ->withArgs(function ($key) {
                expect($key)->toEqual('framework'.DIRECTORY_SEPARATOR.'command-command-name');

                return true;
            })
            ->andReturn(true);

        $mutex->create(new NamedCommand);
    });

    test('an isolatable command appends its isolatable id', function () {
        [$mutex, $factory, $repository] = cacheMutexUnderTest();

        expectCacheMutexUsesStore($factory, $repository);

        $repository->shouldReceive('getStore')
            ->with('test')
            ->andReturn($repository);

        $repository->shouldReceive('add')
            ->once()
            ->withArgs(function ($key) {
                expect($key)->toEqual('framework'.DIRECTORY_SEPARATOR.'command-command-name-isolated');

                return true;
            })
            ->andReturn(true);

        $mutex->create(new IsolatableNamedCommand);
    });
});
