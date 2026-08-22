<?php

use Voyager\Config\Repository;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\Contracts\Workflows\Awaitable;
use Voyager\Vessel\Vessel;
use Voyager\Workflows\AsyncRuntimeDriver;
use Voyager\Workflows\AsyncRuntimeManager;
use Voyager\Workflows\Runtimes\FiberRuntime;
use Voyager\Workflows\Runtimes\SyncRuntime;

function workflowManager(array $config = []): AsyncRuntimeManager
{
    $vessel = new Vessel;
    $vessel->instance('config', new Repository($config));

    return new AsyncRuntimeManager($vessel);
}

test('the manager resolves the runtime named in config', function () {
    $manager = workflowManager(['workflows' => ['runtime' => 'fiber']]);

    expect($manager->driver())->toBeInstanceOf(FiberRuntime::class);
});

test('the manager falls back to the sync runtime with no config', function () {
    expect(workflowManager()->driver())->toBeInstanceOf(SyncRuntime::class)
        ->and(workflowManager()->getDefaultDriver())->toBe('sync');
});

test('the manager accepts a driver enum', function () {
    $manager = workflowManager();

    expect($manager->driver(AsyncRuntimeDriver::FIBER))->toBeInstanceOf(FiberRuntime::class)
        ->and($manager->driver(AsyncRuntimeDriver::SYNC))->toBeInstanceOf(SyncRuntime::class);
});

test('resolved runtimes are reused', function () {
    $manager = workflowManager();

    expect($manager->driver('sync'))->toBe($manager->driver('sync'));
});

test('an unknown runtime name is rejected', function () {
    expect(fn () => workflowManager()->driver('telepathy'))
        ->toThrow(InvalidArgumentException::class, 'Driver [telepathy] not supported.');
});

test('a custom runtime can be registered without touching the package', function () {
    $custom = new class extends SyncRuntime {};

    $manager = workflowManager(['workflows' => ['runtime' => 'homemade']]);
    $manager->extend('homemade', fn () => $custom);

    expect($manager->driver())->toBe($custom);
});

test('the manager forwards runtime calls to the default driver', function () {
    $manager = workflowManager();

    $awaitable = $manager->async(fn () => 'forwarded');

    expect($awaitable)->toBeInstanceOf(Awaitable::class)
        ->and($manager->await($awaitable))->toBe('forwarded');
});

test('every shipped runtime satisfies the contract', function (string $runtime) {
    expect(new $runtime)->toBeInstanceOf(AsyncRuntime::class);
})->with('async runtimes');
