<?php

require_once __DIR__.'/Fixtures/tasks.php';

use Voyager\Concurrency\RunTask;
use Venusian\Tests\Concurrency\Fixtures\Tasks;
use Voyager\Concurrency\PoolDriver;
use Voyager\Concurrency\SyncDriver;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Concurrency\ConcurrencyManager;
use Voyager\Contracts\IOPools\WorkerPools\RemoteException;
use Venusian\Tests\Log\Fixtures\RecordingPool;
use Venusian\Tests\Concurrency\Fixtures\ConcurrencyApp;

beforeEach(fn () => $this->app = ConcurrencyApp::boot());
afterEach(fn () => ConcurrencyApp::tearDown($this->app, $this));

it('runs tasks in the workers and hands back their results by key', function () {
    $results = $this->app->get('concurrency')->driver('pool')->run([
        'sum' => Tasks\sum(20, 22),
        'pid' => Tasks\pid(),
        'base' => Tasks\basePath(),
    ]);

    expect(array_keys($results))->toBe(['sum', 'pid', 'base'])
        ->and($results['sum'])->toBe(42)
        ->and($results['pid'])->not->toBe(getmypid())
        ->and($results['base'])->toBe(dirname(__DIR__, 2))
        ->and($this->app->get('process-workers')->workerCount())->toBeGreaterThan(0);
});

it('rethrows a task\'s exception as its own class, the worker\'s trace behind it', function () {
    try {
        $this->app->get('concurrency')->driver('pool')->run([
            'fine' => Tasks\returns('ok'),
            'declined' => Tasks\declines(),
        ]);
    } catch (DomainException $e) {
        expect($e->getMessage())->toBe('card declined')
            ->and($e->getPrevious())->toBeInstanceOf(RemoteException::class)
            ->and($e->getPrevious()->remote_trace)->toContain('RunTask');

        return;
    }

    $this->fail('The task\'s DomainException was not rethrown.');
});

it('runs tasks without blocking and delivers their results together as mail', function () {
    $promise = $this->app->get('concurrency')->driver('pool')->async([
        'first' => Tasks\returns('one'),
        'second' => Tasks\returns('two'),
    ], 'reports');

    expect($promise->settled())->toBeFalse();

    $mail = ConcurrencyApp::mail($this->app, 'reports');

    expect($mail->name())->toBe('concurrency:reports')
        ->and($mail->successful())->toBeTrue()
        ->and($mail->results)->toBe(['first' => 'one', 'second' => 'two'])
        ->and($promise->wait())->toBe(['first' => 'one', 'second' => 'two']);
});

it('delivers the results and the failures, and rejects with the first failure in key order', function () {
    $promise = $this->app->get('concurrency')->driver('pool')->async([
        'fine' => Tasks\returns('ok'),
        'declined' => Tasks\declines(),
        'lost' => Tasks\tooLong(),
    ], 'mixed');

    $mail = ConcurrencyApp::mail($this->app, 'mixed');

    expect($mail->successful())->toBeFalse()
        ->and($mail->results)->toBe(['fine' => 'ok'])
        ->and(array_keys($mail->failures))->toBe(['declined', 'lost'])
        ->and($mail->failures['lost'])->toBeInstanceOf(LengthException::class)
        ->and(fn () => $promise->wait())->toThrow(DomainException::class, 'card declined');
});

it('names the mail "concurrency:default" when async() is given no name', function () {
    $this->app->get('concurrency')->driver('pool')->async(Tasks\returns('unnamed'));

    expect(ConcurrencyApp::mail($this->app, 'default')->results)->toBe(['unnamed']);
});

it('settles an empty task list at once', function () {
    $driver = $this->app->get('concurrency')->driver('pool');

    expect($driver->run([]))->toBe([])
        ->and($driver->async([], 'empty')->wait())->toBe([]);
});

it('fails the one task whose closure can\'t be sent, and runs the rest', function () {
    $generator = (function () { yield 1; })();

    $promise = $this->app->get('concurrency')->driver('pool')->async([
        'fine' => Tasks\returns('ok'),
        'unsendable' => Tasks\holds($generator),
    ], 'unsendable');

    $mail = ConcurrencyApp::mail($this->app, 'unsendable');

    expect($mail->results)->toBe(['fine' => 'ok'])
        ->and(array_keys($mail->failures))->toBe(['unsendable'])
        ->and($mail->failures['unsendable']->getMessage())->toBe("Serialization of 'Generator' is not allowed")
        ->and(fn () => $promise->wait())->toThrow(Exception::class, "Serialization of 'Generator' is not allowed");
});

it('fails only the task whose closure a worker can\'t rebuild', function () {
    $written_here = static fn () => 'the class this was written in doesn\'t load in a worker';

    $promise = $this->app->get('concurrency')->driver('pool')->async([
        'fine' => Tasks\returns('ok'),
        'stranger' => $written_here,
    ], 'strangers');

    $mail = ConcurrencyApp::mail($this->app, 'strangers');

    expect($mail->results)->toBe(['fine' => 'ok'])
        ->and($mail->failures['stranger'])->toBeInstanceOf(ErrorException::class)
        ->and($mail->failures['stranger']->getMessage())->toContain('not found')
        ->and($mail->failures['stranger']->getPrevious())->toBeInstanceOf(RemoteException::class);
});

it('sends tasks to the thread workers when they are on', function () {
    $threads = new RecordingPool($this->app->get(Loop::class));
    $this->app->registerInstance('thread-workers', $threads);

    $results = $this->app->get('concurrency')->driver('pool')->run(['threaded' => Tasks\returns('on a thread')]);

    expect($results)->toBe(['threaded' => 'on a thread'])
        ->and($threads->gigs)->toHaveCount(1)
        ->and($threads->gigs[0])->toBeInstanceOf(RunTask::class)
        ->and($this->app->get('process-workers')->workerCount())->toBe(0);
});

it('runs tasks on a real thread pool', function () {
    $pool = new Voyager\IOPools\WorkerPools\Thread\ThreadWorkerPool(
        $this->app->get(Loop::class), 2, dirname(__DIR__, 2), Voyager\IOPools\WorkerPools\WorkerPoolManager::autoloader(),
    );
    $this->app->registerInstance('thread-workers', $pool);

    $driver = new PoolDriver($this->app, 'thread');

    expect($driver->run(['a' => Tasks\returns('from a thread'), 'pid' => Tasks\pid()]))
        ->toBe(['a' => 'from a thread', 'pid' => getmypid()]);
})->skip(! PHP_ZTS || ! extension_loaded('parallel'), 'needs a ZTS build with ext-parallel');

it('says which pool is missing or unknown', function () {
    expect(fn () => new PoolDriver($this->app, 'thread')->run(Tasks\returns(null)))
        ->toThrow(InvalidArgumentException::class, 'The thread workers are off: enable io-pools.pool_workers.threads for the pool concurrency driver.')
        ->and(fn () => new PoolDriver($this->app, 'gpu')->run(Tasks\returns(null)))
        ->toThrow(InvalidArgumentException::class, 'The pool concurrency driver has no "gpu" pool: use \'auto\', \'thread\' or \'process\'.');
});

it('runs the default driver\'s tasks without blocking, or says it can\'t', function () {
    $manager = $this->app->get('concurrency');

    expect($manager)->toBeInstanceOf(ConcurrencyManager::class)
        ->and($manager->driver('sync'))->toBeInstanceOf(SyncDriver::class)
        ->and($manager->driver('sync')->run(['a' => Tasks\returns(1), 'b' => Tasks\returns(2)]))->toBe(['a' => 1, 'b' => 2]);

    $manager->setDefaultInstance('pool');
    expect($manager->async(['a' => Tasks\returns('pooled')])->wait())->toBe(['a' => 'pooled']);

    $manager->setDefaultInstance('sync');
    expect(fn () => $manager->async(Tasks\returns(null)))
        ->toThrow(InvalidArgumentException::class, 'The [sync] concurrency driver only runs tasks blocking: async() needs the process or pool driver.');

    $manager->setDefaultInstance('fork');
    expect(fn () => $manager->async(Tasks\returns(null)))->toThrow(RuntimeException::class);
});
