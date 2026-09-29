<?php

use Voyager\IOPools\EventLoop;
use Voyager\IOPools\LoopWaiter;
use Voyager\IOPools\ResourceRegistry;
use Voyager\IOPools\Waiter\KqueueWaiterBackend;
use Voyager\IOPools\Waiter\EpollWaiterBackend;
use Voyager\IOPools\Waiter\StreamSelectWaiterBackend;
use Voyager\IOPools\WorkerPools\WorkerPoolManager;
use Voyager\IOPools\WorkerPools\Thread\ThreadWorkerPool;
use Voyager\IOPools\WorkerPools\Process\ProcessWorkerPool;
use Voyager\IOPools\PromiseEngines\GuzzlePromiseEngine;
use Voyager\Contracts\IOPools\IOPoolsException;
use Voyager\Contracts\IOPools\WorkerPools\RemoteException;
use Voyager\Contracts\IOPools\WorkerPools\DeadWorkerException;
use Venusian\Tests\IOPools\Fixtures\ExitGig;
use Venusian\Tests\IOPools\Fixtures\ThrowGig;
use Venusian\Tests\IOPools\Fixtures\ReturnGig;

/** The loop runs on the best backend this machine has, as the auto driver would pick it. */
function poolLoop(): EventLoop
{
    $registry = new ResourceRegistry();
    $backend = match (true) {
        extension_loaded('epoll') => new EpollWaiterBackend(),
        extension_loaded('kqueue') => new KqueueWaiterBackend(),
        default => new StreamSelectWaiterBackend(),
    };

    return new EventLoop($registry, new LoopWaiter($registry, $backend, 5_000_000), new GuzzlePromiseEngine());
}

/**
 * Every pool this build can run, each on its own loop. Workers boot this repository as their app.
 * The tests type their parameter Closure, so Pest hands the factory over instead of calling it.
 */
dataset('pools', function () {
    $base = dirname(__DIR__, 2);

    yield 'process' => [fn (int $max = 2) => new ProcessWorkerPool(poolLoop(), $max, $base, WorkerPoolManager::autoloader())];

    if (PHP_ZTS && extension_loaded('parallel')) {
        yield 'thread' => [fn (int $max = 2) => new ThreadWorkerPool(poolLoop(), $max, $base, WorkerPoolManager::autoloader())];
    }
});

it('runs a gig off the loop and hands back its return value', function (Closure $make) {
    $pool = $make();

    $result = $pool->submit(new ReturnGig('hello'))->wait();

    expect($result['value'])->toBe('hello')
        ->and($pool instanceof ProcessWorkerPool ? $result['pid'] !== getmypid() : true)->toBeTrue();

    $pool->shutDown();
})->with('pools');

it('rejects with the remote class and message when a gig throws', function (Closure $make) {
    $pool = $make();

    try {
        $pool->submit(new ThrowGig('nope'))->wait();
        $this->fail('The gig should have rejected.');
    } catch (RemoteException $e) {
        expect($e->remote_class)->toBe(DomainException::class)
            ->and($e->getMessage())->toBe('DomainException: nope')
            ->and($e->remote_trace)->not->toBe('');
    } finally {
        $pool->shutDown();
    }
})->with('pools');

it('keeps using the same worker after a gig throws', function (Closure $make) {
    $pool = $make(1);

    expect(fn () => $pool->submit(new ThrowGig('first'))->wait())->toThrow(RemoteException::class);
    expect($pool->submit(new ReturnGig('second'))->wait()['value'])->toBe('second')
        ->and($pool->workerCount())->toBe(1);

    $pool->shutDown();
})->with('pools');

it('queues gigs beyond its workers and runs every one', function (Closure $make) {
    $pool = $make(2);

    $promises = array_map(fn (int $n) => $pool->submit(new ReturnGig($n, 30)), range(1, 5));

    expect($pool->workerCount())->toBe(2)
        ->and(array_map(fn ($promise) => $promise->wait()['value'], $promises))->toBe([1, 2, 3, 4, 5]);

    $pool->shutDown();
})->with('pools');

it('rejects a gig that cannot be serialized without spending a worker on it', function (Closure $make) {
    $pool = $make(1);

    expect(fn () => $pool->submit(new ReturnGig(fn () => 1))->wait())
        ->toThrow(IOPoolsException::class, "This gig can't be sent to a worker");
    expect($pool->submit(new ReturnGig('after'))->wait()['value'])->toBe('after');

    $pool->shutDown();
})->with('pools');

it('lets run() end once the gigs settle: idle workers never keep the loop alive', function (Closure $make) {
    $pool = $make();
    $loop = (fn () => $this->loop)->call($pool);
    $promise = $pool->submit(new ReturnGig('done'));

    expect($loop->run())->toBe(0)
        ->and($promise->settled())->toBeTrue();
})->with('pools');

it('rejects queued gigs and stops its workers when the loop stops', function (Closure $make) {
    $pool = $make(1);
    $loop = (fn () => $this->loop)->call($pool);

    $running = $pool->submit(new ReturnGig('slow', 2000));
    $queued = $pool->submit(new ReturnGig('never'));
    $loop->at(0.3, fn () => $loop->stop());
    $loop->run();

    expect($pool->workerCount())->toBe(0)
        ->and($running->rejected())->toBeTrue()
        ->and($queued->rejected())->toBeTrue();
})->with('pools');

it('rejects with the worker\'s last words when it exits mid-gig, then replaces it', function () {
    $pool = new ProcessWorkerPool(poolLoop(), 1, dirname(__DIR__, 2), WorkerPoolManager::autoloader());

    expect(fn () => $pool->submit(new ExitGig())->wait())
        ->toThrow(DeadWorkerException::class, 'exited before its gig finished. Last output: leaving now');
    expect($pool->submit(new ReturnGig('fresh'))->wait()['value'])->toBe('fresh')
        ->and($pool->workerCount())->toBe(1);

    $pool->shutDown();
});

it('replaces an idle worker that died between gigs', function () {
    $pool = new ProcessWorkerPool(poolLoop(), 1, dirname(__DIR__, 2), WorkerPoolManager::autoloader());

    $first = $pool->submit(new ReturnGig('first'))->wait();
    posix_kill($first['pid'], SIGKILL);
    usleep(100_000);

    $second = $pool->submit(new ReturnGig('second'))->wait();

    expect($second['value'])->toBe('second')
        ->and($second['pid'])->not->toBe($first['pid']);

    $pool->shutDown();
})->skip(! extension_loaded('posix'), 'needs posix');

it('refuses to build a thread pool on a build that cannot run threads', function () {
    expect(fn () => new ThreadWorkerPool(poolLoop(), 1, dirname(__DIR__, 2), WorkerPoolManager::autoloader()))
        ->toThrow(IOPoolsException::class, 'The thread pool needs a ZTS build of PHP with ext-parallel loaded.');
})->skip(PHP_ZTS && extension_loaded('parallel'), 'this build can run threads');
