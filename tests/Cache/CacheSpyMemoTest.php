<?php

use Voyager\Cache\CacheManager;
use Voyager\Config\Repository as ConfigRepository;
use Voyager\Vessel\Vessel as Container;
use Voyager\NutsAndBolts\MagicAliases\Cache;
use Voyager\MagicAliases\MagicAlias;
use Mockery\LegacyMockInterface;

beforeEach(function () {
    $container = new Container;

    $container->instance('config', new ConfigRepository([
        'cache' => [
            'default' => 'array',
            'stores' => [
                'array' => [
                    'driver' => 'array',
                ],
            ],
        ],
    ]));

    $container->instance('cache', new CacheManager($container));

    MagicAlias::setMagicAliasApplication($container);
});

afterEach(function () {
    MagicAlias::clearResolvedInstances();
    MagicAlias::setMagicAliasApplication(null);
});

test('a cache spy works with a memoized cache', function () {
    $cache = Cache::spy();

    Cache::memo()->remember('key', 60, fn () => 'bar');

    $cache->shouldHaveReceived('memo')->once();
});

test('a cache spy tracks remember on a memoized cache as described in the issue', function () {
    $cache = Cache::spy();

    $memoizedCache = Cache::memo();
    $value = $memoizedCache->remember('key', 60, fn () => 'bar');

    expect($value)->toBe('bar');

    $memoizedCache->shouldHaveReceived('remember')->once()->with('key', 60, Mockery::type(Closure::class));
});

test('a cache spy tracks remember calls on a memoized cache', function () {
    $cache = Cache::spy();

    $memoizedCache = Cache::memo();
    $memoizedCache->remember('key', 60, fn () => 'bar');

    $memoizedCache->shouldHaveReceived('remember')->once()->with('key', 60, Mockery::type(Closure::class));
});

test('cache memo returns a spied repository', function () {
    $cache = Cache::spy();

    $memoizedCache = Cache::memo();

    expect($memoizedCache)->toBeInstanceOf(LegacyMockInterface::class);

    $memoizedCache->remember('key', 60, fn () => 'bar');

    $memoizedCache->shouldHaveReceived('remember')->once();
});
