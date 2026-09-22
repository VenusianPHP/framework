<?php

use Venusian\Tests\IOPools\Fixtures\AddNumbers;
use Voyager\Config\Repository;
use Voyager\Contracts\IOPools\EventLoopException;
use Voyager\IOPools\EventLoop;
use Voyager\IOPools\ProcessPool;
use Voyager\IOPools\ThreadPool;
use Voyager\IOPools\WorkerPoolManager;
use Voyager\Vessel\ControlPanel;

function poolManager(array $thread_pool = []): WorkerPoolManager
{
    $root = dirname(__DIR__, 2);

    $vessel = new ControlPanel;
    $vessel->registerInstance('config', new Repository(['io-pools' => ['thread_pool' => $thread_pool + [
        'size' => 1,
        'max_jobs' => null,
        'autoload_path' => $root.'/vendor/autoload.php',
        'base_path' => $root,
        'worker_script' => null,
        'php_args' => [],
        'sweep_seconds' => 0.1,
    ]]]));
    $vessel->registerInstance(EventLoop::class, new EventLoop);

    return new WorkerPoolManager($vessel);
}

it('defaults to the process driver', function () {
    $pool = poolManager()->driver();

    expect($pool)->toBeInstanceOf(ProcessPool::class)
        ->and($pool->submit(new AddNumbers(2, 3))->wait())->toBe(5);

    $pool->shutDown();
});

it('builds the driver the config names', function () {
    $pool = poolManager(['driver' => 'thread'])->driver();

    expect($pool)->toBeInstanceOf(ThreadPool::class)
        ->and($pool->submit(new AddNumbers(2, 3))->wait())->toBe(5);

    $pool->shutDown();
})->skip(! extension_loaded('parallel'), 'ext-parallel not loaded');

it('says why the thread driver is unavailable', function () {
    expect(fn () => poolManager(['driver' => 'thread'])->driver())
        ->toThrow(EventLoopException::class, 'ext-parallel');
})->skip(extension_loaded('parallel'), 'ext-parallel is loaded');

it('hands back the same pool each time', function () {
    $manager = poolManager();

    expect($manager->driver())->toBe($manager->driver());

    $manager->driver()->shutDown();
});
