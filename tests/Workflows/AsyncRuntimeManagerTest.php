<?php

use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\Workflows\AsyncRuntime;
use Voyager\IOPools\EventLoop;
use Voyager\Vessel\ControlPanel as Vessel;
use Voyager\Workflows\AsyncRuntimeDriver;
use Voyager\Workflows\AsyncRuntimeManager;
use Voyager\Workflows\Runtimes\LoopRuntime;

function workflowManager(array $config = []): AsyncRuntimeManager
{
    $vessel = new Vessel;
    $vessel->registerInstance('config', new Repository($config));
    $vessel->registerInstance(Loop::class, new EventLoop);

    return new AsyncRuntimeManager($vessel);
}

test('the manager resolves the runtime named in config', function () {
    $manager = workflowManager(['workflows' => ['runtime' => 'isolated']]);

    expect($manager->driver())->toBeInstanceOf(LoopRuntime::class);
});

test('falls back to the loop runtime with no config', function () {
    expect(workflowManager()->driver())->toBeInstanceOf(LoopRuntime::class)
        ->and(workflowManager()->getDefaultDriver())->toBe('loop');
});

test('the manager accepts a driver enum', function () {
    $manager = workflowManager();

    expect($manager->driver(AsyncRuntimeDriver::ISOLATED))->toBeInstanceOf(LoopRuntime::class)
        ->and($manager->driver(AsyncRuntimeDriver::LOOP))->toBeInstanceOf(LoopRuntime::class);
});

test('resolved runtimes are reused', function () {
    $manager = workflowManager();

    expect($manager->driver('loop'))->toBe($manager->driver('loop'));
});

test('an unknown runtime name is rejected', function () {
    expect(fn () => workflowManager()->driver('telepathy'))
        ->toThrow(InvalidArgumentException::class, 'Driver [telepathy] not supported.');
});

test('a custom runtime can be registered without touching the package', function () {
    $custom = new class(new EventLoop) extends LoopRuntime {};

    $manager = workflowManager(['workflows' => ['runtime' => 'homemade']]);
    $manager->extend('homemade', fn () => $custom);

    expect($manager->driver())->toBe($custom);
});

test('the manager forwards runtime calls to the default driver', function () {
    $manager = workflowManager();

    $awaitable = $manager->async(fn () => 'forwarded');

    expect($awaitable)->toBeInstanceOf(Promise::class)
        ->and($manager->await($awaitable))->toBe('forwarded');
});

test('every shipped runtime satisfies the contract', function (LoopRuntime $runtime) {
    expect($runtime)->toBeInstanceOf(AsyncRuntime::class);
})->with('async runtimes');

test('the loop driver runs on the app\'s loop; isolated does not', function () {
    $loop = new EventLoop;
    $vessel = new Vessel;
    $vessel->registerInstance('config', new Repository([]));
    $vessel->registerInstance(Loop::class, $loop);
    $manager = new AsyncRuntimeManager($vessel);

    expect($manager->driver('loop')->loop())->toBe($loop)
        ->and($manager->driver('isolated')->loop())->not->toBe($loop);
});
