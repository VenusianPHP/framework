<?php

use Voyager\Cache\ArrayStore;
use Voyager\Cache\CacheManager;
use Voyager\Cache\NullStore;
use Voyager\Config\Repository;
use Voyager\Vessel\Vessel as Container;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Events\Dispatcher as Event;

/** A container bound with the given user cache config. */
function containerWithCacheConfig(array $userConfig): Container
{
    $app = new Container;
    $app->singleton('config', fn () => new Repository($userConfig));

    return $app;
}

test('a custom driver closure is bound to the cache manager', function () {
    // A fixed name stands in for the PHPUnit __CLASS__ this test used to key
    // its store and driver by — that constant has no enclosing class in a
    // Pest file, and the driver's actual name is otherwise irrelevant here:
    // extend() rebinds the closure to the manager regardless of which name
    // it was registered under.
    $driverName = 'cache_manager_test_custom_driver';

    $cacheManager = new CacheManager([
        'config' => [
            'cache.stores.'.$driverName => [
                'driver' => $driverName,
            ],
        ],
    ]);
    $driver = function () {
        return $this;
    };
    $cacheManager->extend($driverName, $driver);

    expect($cacheManager->store($driverName))->toEqual($cacheManager);
});

test('a custom driver overrides internal drivers', function () {
    $userConfig = [
        'cache' => [
            'stores' => [
                'my_store' => [
                    'driver' => 'array',
                ],
            ],
        ],
    ];

    $app = containerWithCacheConfig($userConfig);
    $cacheManager = new CacheManager($app);

    $myArrayDriver = (object) ['flag' => 'mm(u_u)mm'];
    $cacheManager->extend('array', fn () => $myArrayDriver);

    $driver = $cacheManager->store('my_store');

    expect($driver->flag)->toBe('mm(u_u)mm');
});

test('it can build repositories', function () {
    $app = containerWithCacheConfig([]);
    $cacheManager = new CacheManager($app);

    $arrayCache = $cacheManager->build(['driver' => 'array']);
    $nullCache = $cacheManager->build(['driver' => 'null']);

    expect($arrayCache->getStore())->toBeInstanceOf(ArrayStore::class)
        ->and($nullCache->getStore())->toBeInstanceOf(NullStore::class);
});

test('it makes a repository when the container has no dispatcher', function () {
    $userConfig = [
        'cache' => [
            'stores' => [
                'my_store' => [
                    'driver' => 'array',
                ],
            ],
        ],
    ];

    $app = containerWithCacheConfig($userConfig);
    expect($app->bound(Dispatcher::class))->toBeFalse();

    $cacheManager = new CacheManager($app);
    $repo = $cacheManager->repository($theStore = new NullStore);

    expect($repo->getEventDispatcher())->toBeNull()
        ->and($repo->getStore())->toBe($theStore);

    // binding dispatcher after the repo's birth will have no effect.
    $app->bind(Dispatcher::class, fn () => new Event);

    expect($repo->getEventDispatcher())->toBeNull()
        ->and($repo->getStore())->toBe($theStore);

    $cacheManager = new CacheManager($app);
    $repo = $cacheManager->repository(new NullStore);
    // now that the $app has a Dispatcher, the newly born repository will also have one.
    expect($repo->getEventDispatcher())->not->toBeNull();
});

test('it refreshes the dispatcher on all stores', function () {
    $userConfig = [
        'cache' => [
            'stores' => [
                'store_1' => [
                    'driver' => 'array',
                ],
                'store_2' => [
                    'driver' => 'array',
                ],
            ],
        ],
    ];

    $app = containerWithCacheConfig($userConfig);
    $cacheManager = new CacheManager($app);
    $repo1 = $cacheManager->store('store_1');
    $repo2 = $cacheManager->store('store_2');

    expect($repo1->getEventDispatcher())->toBeNull()
        ->and($repo2->getEventDispatcher())->toBeNull();

    $dispatcher = new Event;
    $app->bind(Dispatcher::class, fn () => $dispatcher);

    $cacheManager->refreshEventDispatcher();

    expect($repo1)->not->toBe($repo2)
        ->and($repo1->getEventDispatcher())->toBe($dispatcher)
        ->and($repo2->getEventDispatcher())->toBe($dispatcher);
});

test('setDefaultDriver changes the global config', function () {
    $userConfig = [
        'cache' => [
            'default' => 'store_1',
            'stores' => [
                'store_1' => [
                    'driver' => 'array',
                ],
                'store_2' => [
                    'driver' => 'array',
                ],
            ],
        ],
    ];

    $app = containerWithCacheConfig($userConfig);
    $cacheManager = new CacheManager($app);

    $cacheManager->setDefaultDriver('><((((@>');

    expect($app->get('config')->get('cache.default'))->toEqual('><((((@>');
});

test('it purges memoized store objects', function () {
    $userConfig = [
        'cache' => [
            'stores' => [
                'store_1' => [
                    'driver' => 'array',
                ],
                'store_2' => [
                    'driver' => 'null',
                ],
            ],
        ],
    ];

    $app = containerWithCacheConfig($userConfig);
    $cacheManager = new CacheManager($app);

    $repo1 = $cacheManager->store('store_1');
    $repo2 = $cacheManager->store('store_1');

    $repo3 = $cacheManager->store('store_2');
    $repo4 = $cacheManager->store('store_2');
    $repo5 = $cacheManager->store('store_2');

    expect($repo1)->toBe($repo2)
        ->and($repo3)->toBe($repo4)
        ->and($repo3)->toBe($repo5)
        ->and($repo1)->not->toBe($repo5);

    $cacheManager->purge('store_1');

    // Make sure a now object is built this time.
    $repo6 = $cacheManager->store('store_1');
    expect($repo1)->not->toBe($repo6);

    // Make sure Purge does not delete all objects.
    $repo7 = $cacheManager->store('store_2');
    expect($repo3)->toBe($repo7);
});

test('forgetDriver forgets the resolved store', function () {
    $cacheManager = Mockery::mock(CacheManager::class)
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $cacheManager->shouldReceive('resolve')
        ->withArgs(['array'])
        ->times(4)
        ->andReturn(new ArrayStore);

    $cacheManager->shouldReceive('getDefaultDriver')
        ->once()
        ->andReturn('array');

    foreach (['array', ['array'], null] as $option) {
        $cacheManager->store('array');
        $cacheManager->store('array');
        $cacheManager->forgetDriver($option);
        $cacheManager->store('array');
        $cacheManager->store('array');
    }
});

test('forgetDriver actually forgets', function () {
    $cacheManager = new CacheManager([
        'config' => [
            'cache.stores.forget' => [
                'driver' => 'forget',
            ],
        ],
    ]);
    $cacheManager->extend('forget', function () {
        return new ArrayStore;
    });

    $cacheManager->store('forget')->forever('foo', 'bar');
    expect($cacheManager->store('forget')->get('foo'))->toBe('bar');

    $cacheManager->forgetDriver('forget');
    expect($cacheManager->store('forget')->get('foo'))->toBeNull();
});

test('an unknown driver throws an exception', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Driver [unknown_taxi_driver] is not supported.');

    $userConfig = [
        'cache' => [
            'stores' => [
                'my_store' => [
                    'driver' => 'unknown_taxi_driver',
                ],
            ],
        ],
    ];

    $app = containerWithCacheConfig($userConfig);

    $cacheManager = new CacheManager($app);

    $cacheManager->store('my_store');
});

test('an unknown store throws an exception', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cache store [alien_store] is not defined.');

    $userConfig = [
        'cache' => [
            'stores' => [
                'my_store' => [
                    'driver' => 'array',
                ],
            ],
        ],
    ];

    $app = containerWithCacheConfig($userConfig);

    $cacheManager = new CacheManager($app);

    $cacheManager->store('alien_store');
});

test('it makes a repository without a dispatcher when events are disabled', function () {
    $userConfig = [
        'cache' => [
            'stores' => [
                'my_store' => [
                    'driver' => 'array',
                ],
                'my_store_without_events' => [
                    'driver' => 'array',
                    'events' => false,
                ],
            ],
        ],
    ];

    $app = containerWithCacheConfig($userConfig);
    $app->bind(Dispatcher::class, fn () => new Event);

    $cacheManager = new CacheManager($app);

    // The repository will have an event dispatcher
    $repo = $cacheManager->store('my_store');
    expect($repo->getEventDispatcher())->not->toBeNull();

    // This repository will not have an event dispatcher as 'with_events' is false
    $repoWithoutEvents = $cacheManager->store('my_store_without_events');
    expect($repoWithoutEvents->getEventDispatcher())->toBeNull();
});
