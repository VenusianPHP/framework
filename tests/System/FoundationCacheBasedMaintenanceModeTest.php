<?php

use Voyager\Contracts\Cache\Factory;
use Voyager\Contracts\Cache\Repository;
use Voyager\System\CacheBasedMaintenanceMode;

test('it determines whether maintenance mode is active', function () {
    $cache = Mockery::mock(Factory::class, Repository::class);
    $cache->shouldReceive('store')->with('store-key')->andReturnSelf();

    $manager = new CacheBasedMaintenanceMode($cache, 'store-key', 'key');

    $cache->shouldReceive('has')->once()->with('key')->andReturnFalse();
    expect($manager->active())->toBeFalse();

    $cache->shouldReceive('has')->once()->with('key')->andReturnTrue();
    expect($manager->active())->toBeTrue();
});

test('it retrieves the payload from the cache', function () {
    $cache = Mockery::mock(Factory::class, Repository::class);
    $cache->shouldReceive('store')->with('store-key')->andReturnSelf();

    $manager = new CacheBasedMaintenanceMode($cache, 'store-key', 'key');

    $cache->shouldReceive('get')->once()->with('key')->andReturn(['payload']);
    expect($manager->data())->toBe(['payload']);
});

test('it stores the payload in the cache', function () {
    $cache = Mockery::spy(Factory::class, Repository::class);
    $cache->shouldReceive('store')->with('store-key')->andReturnSelf();

    $manager = new CacheBasedMaintenanceMode($cache, 'store-key', 'key');
    $manager->activate(['payload']);

    $cache->shouldHaveReceived('put')->once()->with('key', ['payload']);
});

test('it removes the payload from the cache', function () {
    $cache = Mockery::spy(Factory::class, Repository::class);
    $cache->shouldReceive('store')->with('store-key')->andReturnSelf();

    $manager = new CacheBasedMaintenanceMode($cache, 'store-key', 'key');
    $manager->deactivate();

    $cache->shouldHaveReceived('forget')->once()->with('key');
});
