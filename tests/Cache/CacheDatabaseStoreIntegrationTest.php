<?php

use Voyager\Cache\CacheManager;
use Voyager\Cache\DatabaseLock;
use Voyager\Config\Repository;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Schema\Blueprint;
use Voyager\Vessel\Vessel;

/**
 * A CacheManager wired to the "database" driver against a real in-memory
 * sqlite connection, using the same "cache"/"cache_locks" schema that
 * CacheTableCommand generates.
 */
function databaseCacheManager(): CacheManager
{
    $db = new DB;
    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->getConnection()->getSchemaBuilder()->create('cache', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->mediumText('value');
        $table->bigInteger('expiration')->index();
    });

    $db->getConnection()->getSchemaBuilder()->create('cache_locks', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->string('owner');
        $table->bigInteger('expiration')->index();
    });

    $app = new Vessel;
    $app->instance('db', $db->getDatabaseManager());
    $app->instance('config', new Repository([
        'cache.stores.database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
        ],
        'cache.prefix' => '',
    ]));

    return new CacheManager($app);
}

test('the database driver is registered and stores real values', function () {
    $repository = databaseCacheManager()->store('database');

    expect($repository->put('foo', 'bar', 60))->toBeTrue()
        ->and($repository->get('foo'))->toBe('bar')
        ->and($repository->get('missing'))->toBeNull();

    expect($repository->forever('always', 'here'))->toBeTrue()
        ->and($repository->get('always'))->toBe('here');

    expect($repository->forget('foo'))->toBeTrue()
        ->and($repository->get('foo'))->toBeNull();

    expect($repository->flush())->toBeTrue()
        ->and($repository->get('always'))->toBeNull();
});

test('the database driver increments and decrements real rows', function () {
    $repository = databaseCacheManager()->store('database');

    $repository->put('counter', 1, 60);

    expect($repository->increment('counter', 4))->toBe(5)
        ->and($repository->decrement('counter', 2))->toBe(3)
        ->and($repository->get('counter'))->toBe(3);
});

test('the database driver produces a working lock', function () {
    $store = databaseCacheManager()->store('database')->getStore();

    $lock = $store->lock('processing', 10);

    expect($lock)->toBeInstanceOf(DatabaseLock::class)
        ->and($lock->acquire())->toBeTrue()
        ->and($store->lock('processing', 10)->acquire())->toBeFalse()
        ->and($lock->release())->toBeTrue()
        ->and($store->lock('processing', 10)->acquire())->toBeTrue();
});

test('a database lock can be refreshed and restored by owner', function () {
    $store = databaseCacheManager()->store('database')->getStore();

    $lock = $store->lock('refreshable', 10);
    $lock->acquire();

    expect($lock->refresh(20))->toBeTrue();

    $restored = $store->restoreLock('refreshable', $lock->owner());

    expect($restored)->toBeInstanceOf(DatabaseLock::class)
        ->and($restored->isOwnedByCurrentProcess())->toBeTrue();

    $restored->forceRelease();

    expect($store->lock('refreshable', 10)->acquire())->toBeTrue();
});
