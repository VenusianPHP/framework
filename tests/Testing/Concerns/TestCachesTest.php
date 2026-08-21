<?php

use Tests\Testing\Fixtures\TestCachesInstance;
use Voyager\Config\Repository as Config;
use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\MagicAliases\ParallelTesting as ParallelTestingFacade;
use Voyager\Testing\ParallelTesting;
use Voyager\Vessel\Vessel as Container;

beforeEach(function () {
    Container::setInstance($container = new Container);

    MagicAlias::setMagicAliasApplication($container);

    $container->singleton('config', fn () => new Config([
        'cache' => [
            'prefix' => 'myapp_cache_',
        ],
    ]));

    $container->singleton(ParallelTesting::class, fn ($app) => new ParallelTesting($app));

    $_SERVER['LARAVEL_PARALLEL_TESTING'] = 1;
});

afterEach(function () {
    Container::setInstance(null);
    ParallelTestingFacade::clearResolvedInstance();
    MagicAlias::setMagicAliasApplication(null);

    unset($_SERVER['LARAVEL_PARALLEL_TESTING']);

    // Reset static property between tests
    (new ReflectionProperty(TestCachesInstance::class, 'originalCachePrefix'))->setValue(null, null);
});

test('cache prefix appends token', function (string $prefix, string $token, string $expected) {
    Container::getInstance()['config']->set('cache.prefix', $prefix);
    Container::getInstance()->make(ParallelTesting::class)->resolveTokenUsing(fn () => $token);

    $instance = new TestCachesInstance;
    (new ReflectionProperty($instance::class, 'originalCachePrefix'))->setValue(null, null);

    $result = (new ReflectionMethod($instance, 'parallelSafeCachePrefix'))->invoke($instance);

    expect($result)->toBe($expected);
})->with([
    'with prefix' => ['myapp_cache_', '5', 'myapp_cache_test_5_'],
    'empty prefix' => ['', '3', 'test_3_'],
]);

test('cache prefix preserves original prefix', function () {
    Container::getInstance()->make(ParallelTesting::class)->resolveTokenUsing(fn () => '1');

    $instance = new TestCachesInstance;
    (new ReflectionProperty($instance::class, 'originalCachePrefix'))->setValue(null, null);
    (new ReflectionMethod($instance, 'parallelSafeCachePrefix'))->invoke($instance);

    Container::getInstance()->make(ParallelTesting::class)->resolveTokenUsing(fn () => '2');

    $result = (new ReflectionMethod($instance, 'parallelSafeCachePrefix'))->invoke($instance);

    expect($result)->toBe('myapp_cache_test_2_');
});

test('switch to cache prefix updates config', function () {
    $instance = new TestCachesInstance;
    (new ReflectionMethod($instance, 'switchToCachePrefix'))->invoke($instance, 'new_prefix_');

    expect(Container::getInstance()['config']->get('cache.prefix'))->toBe('new_prefix_');
});

test('boot test cache registers set up test case callback', function () {
    Container::getInstance()->make(ParallelTesting::class)->resolveTokenUsing(fn () => '7');

    $instance = new TestCachesInstance;
    (new ReflectionProperty($instance::class, 'originalCachePrefix'))->setValue(null, null);

    (new ReflectionMethod($instance, 'bootTestCache'))->invoke($instance);

    $parallelTesting = Container::getInstance()->make(ParallelTesting::class);
    $setUpCallbacks = (new ReflectionProperty($parallelTesting, 'setUpTestCaseCallbacks'))->getValue($parallelTesting);

    expect($setUpCallbacks)->toHaveCount(1);
});

test('boot test cache skips isolation if opted out', function () {
    Container::getInstance()->make(ParallelTesting::class)->resolveTokenUsing(fn () => '7');

    $instance = new TestCachesInstance;
    (new ReflectionProperty($instance::class, 'originalCachePrefix'))->setValue(null, null);
    (new ReflectionMethod($instance, 'bootTestCache'))->invoke($instance);

    $_SERVER['LARAVEL_PARALLEL_TESTING_WITHOUT_CACHE'] = 1;

    Container::getInstance()->make(ParallelTesting::class)->callSetUpTestCaseCallbacks(new class {});

    expect(Container::getInstance()['config']->get('cache.prefix'))->toBe('myapp_cache_');

    unset($_SERVER['LARAVEL_PARALLEL_TESTING_WITHOUT_CACHE']);
});

test('switch to cache prefix does not remove resolved drivers', function () {
    $container = Container::getInstance();

    $container->singleton('cache', fn ($app) => new \Voyager\Cache\CacheManager($app));

    $container['config']->set('cache.default', 'array');
    $container['config']->set('cache.stores.array', ['driver' => 'array']);

    $driver = $container['cache']->driver();

    $instance = new TestCachesInstance;
    (new ReflectionMethod($instance, 'switchToCachePrefix'))->invoke($instance, 'new_prefix_');

    expect($container['cache']->driver())->toBe($driver);
});
