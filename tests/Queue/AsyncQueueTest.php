<?php

use Voyager\Contracts\IOPools\Promise;
use Voyager\Queue\Async\AsyncQueue;
use Voyager\Queue\Signals\JobQueued;
use Voyager\Queue\Signals\JobQueueing;
use Venusian\Tests\Queue\Fixtures\QueueApp;
use Venusian\Tests\Queue\Fixtures\RecordJob;

beforeEach(fn () => $this->app = QueueApp::boot());
afterEach(fn () => QueueApp::tearDown($this->app, $this));

dataset('io queues', function () {
    yield 'database' => ['database'];

    if (QueueApp::redisReachable()) {
        yield 'redis' => ['redis'];
    }
});

/** The lines the journal holds after the named connection's jobs ran, pid stripped. */
function workAll(object $test, string $connection): array
{
    QueueApp::run($test->app, ['command' => 'queue:work', 'connection' => $connection, '--stop-when-empty' => true, '--sleep' => 0]);

    return array_map(fn (string $line) => preg_replace('/ @\d+$/', '', $line), QueueApp::lines());
}

it('pushes without blocking and answers with the job\'s id, the signals dispatched once, here', function (string $connection) {
    $heard = [];
    $this->app['signals']->listen(JobQueueing::class, function () use (&$heard) { $heard[] = 'queueing'; });
    $this->app['signals']->listen(JobQueued::class, function (JobQueued $queued) use (&$heard) { $heard[] = 'queued '.gettype($queued->id); });

    $async = $this->app['queue']->connection($connection)->async();
    $pushed = $async->push(new RecordJob(QueueApp::journal(), 'async'));

    expect($async)->toBeInstanceOf(AsyncQueue::class)
        ->and($pushed)->toBeInstanceOf(Promise::class)
        ->and($pushed->wait())->not->toBeNull()
        ->and($heard)->toBe(['queueing', 'queued '.gettype($pushed->wait())])
        ->and($this->app['queue']->connection($connection)->size())->toBe(1)
        ->and(workAll($this, $connection))->toBe(['async']);
})->with('io queues');

it('keeps the order pushes were made in, async and blocking mixed', function (string $connection) {
    $journal = QueueApp::journal();
    $queue = $this->app['queue']->connection($connection);

    $pushes = array_map(fn (int $n) => $queue->async()->push(new RecordJob($journal, "async {$n}")), range(1, 5));
    $queue->push(new RecordJob($journal, 'blocking'));
    $last = $queue->async()->push(new RecordJob($journal, 'async 6'));

    array_map(fn (Promise $push) => $push->wait(), [...$pushes, $last]);

    expect(workAll($this, $connection))->toBe(['async 1', 'async 2', 'async 3', 'async 4', 'async 5', 'blocking', 'async 6']);
})->with('io queues');

it('delays a job from the moment the push was made', function (string $connection) {
    $queue = $this->app['queue']->connection($connection);

    $queue->async()->later(60, new RecordJob(QueueApp::journal(), 'later'))->wait();

    expect($queue->size())->toBe(1)
        ->and(workAll($this, $connection))->toBe([]);
})->with('io queues');

it('offloads the database push to a worker', function () {
    $this->app['queue']->connection('database')->async()->push(new RecordJob(QueueApp::journal(), 'offloaded'))->wait();

    expect($this->app->get('process-workers')->workerCount())->toBeGreaterThan(0);
});

it('holds a job dispatched after commit until the transaction commits, and drops it on a rollback', function () {
    $journal = QueueApp::journal();
    $db = $this->app['db']->connection('sqlite');
    $queue = $this->app['queue']->connection('redis');

    $db->beginTransaction();
    $committed = $queue->async()->push(new RecordJob($journal, 'committed')->afterCommit());
    $held = $committed->settled();
    $db->commit();

    $db->beginTransaction();
    $rolled_back = $queue->async()->push(new RecordJob($journal, 'rolled back')->afterCommit());
    $db->rollBack();

    expect($held)->toBeFalse()
        ->and($committed->wait())->not->toBeNull()
        ->and(fn () => $rolled_back->wait())->toThrow(RuntimeException::class, 'rolled back, so it was never pushed')
        ->and(workAll($this, 'redis'))->toBe(['committed']);
})->skip(fn () => ! QueueApp::redisReachable(), 'needs the local Redis');

it('answers a connection with no I/O of its own right here, and hands back the promise a background push already is', function () {
    $journal = QueueApp::journal();
    $queues = $this->app['queue'];

    $sync = $queues->connection('sync')->async()->push(new RecordJob($journal, 'sync'));
    $ran_sync = QueueApp::lines();
    $background = $queues->connection('background')->async()->push(new RecordJob($journal, 'background'));

    $background->wait();

    expect($ran_sync)->toBe(['sync @'.getmypid()])
        ->and($sync->wait())->not->toBeNull()
        ->and(array_map(fn (string $line) => preg_replace('/ @\d+$/', '', $line), QueueApp::lines()))->toBe(['sync', 'background']);
});

it('rejects a database push made inside an open transaction instead of letting it land outside it', function () {
    $db = $this->app['db']->connection('sqlite');

    $db->beginTransaction();
    $pushed = $this->app['queue']->connection('database')->async()->push(new RecordJob(QueueApp::journal(), 'inside'));
    $db->rollBack();

    expect(fn () => $pushed->wait())->toThrow(LogicException::class, 'has a transaction open');
});
