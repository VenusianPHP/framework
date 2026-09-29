<?php

use Voyager\Signals\QueuedClosure;
use Voyager\Log\Context\Repository;
use Voyager\Queue\Signals\JobFailed;
use Voyager\Database\Schema\Blueprint;
use Voyager\Contracts\Bus\Dispatcher;
use Venusian\Tests\Queue\Fixtures\Greeted;
use Venusian\Tests\Queue\Fixtures\QueueApp;
use Venusian\Tests\Queue\Fixtures\RecordJob;
use Venusian\Tests\Queue\Fixtures\UniqueJob;
use Venusian\Tests\Queue\Fixtures\BatchedJob;
use Venusian\Tests\Queue\Fixtures\ContextJob;
use Venusian\Tests\Queue\Fixtures\FailingJob;
use Venusian\Tests\Queue\Fixtures\RecordGreeting;
use Venusian\Tests\Queue\Fixtures\UniqueGreeting;
use Voyager\Bus\Testing\BatchFake;
use Venusian\Tests\Database\Fixtures\DatabaseApp;

beforeEach(fn () => $this->app = QueueApp::boot());
afterEach(fn () => QueueApp::tearDown($this->app, $this));

/**
 * Closures a job carries, made outside the test case: a closure keeps the class it was made
 * in, and serializing it would carry the test case along.
 */
function journaling(string $journal, string $line): Closure
{
    return static fn () => file_put_contents($journal, "{$line}\n", FILE_APPEND | LOCK_EX);
}

function journalingCatch(string $journal): Closure
{
    return static fn ($signal, Throwable $e) => file_put_contents($journal, "caught {$e->getMessage()}\n", FILE_APPEND | LOCK_EX);
}

function throwingListener(): Closure
{
    return static fn () => throw new DomainException('listener broke');
}

/** Strips the " @pid" a RecordJob adds, for lines where the process doesn't matter. */
function journalLines(): array
{
    return array_map(fn (string $line) => preg_replace('/ @\d+$/', '', $line), QueueApp::lines());
}

it('runs a sync job, a closure and a chain here, in order', function () {
    $journal = QueueApp::journal();

    dispatch(new RecordJob($journal, 'job'));
    dispatch(journaling($journal, 'closure'));
    RecordJob::withChain([new RecordJob($journal, 'second'), new RecordJob($journal, 'third')])->dispatch($journal, 'first');

    expect(QueueApp::lines())->toBe(['job @'.getmypid(), 'closure', 'first @'.getmypid(), 'second @'.getmypid(), 'third @'.getmypid()]);
});

it('queues a ShouldQueue listener as a job, and a queued closure with its catch callbacks', function () {
    $journal = QueueApp::journal();
    $signals = $this->app['signals'];

    $signals->listen(Greeted::class, RecordGreeting::class);
    $signals->listen(Greeted::class, new QueuedClosure(journaling($journal, 'queued closure ran'))->onConnection('sync'));
    $signals->listen(Greeted::class, new QueuedClosure(throwingListener())->onConnection('sync')->catch(journalingCatch($journal)));

    try {
        $signals->dispatch(new Greeted($journal, 'ada'));
    } catch (DomainException) {
        // sync rethrows the listener's failure once its catch callbacks ran
    }

    expect(QueueApp::lines())->toBe(['greeted ada as a job: yes', 'queued closure ran', 'caught listener broke']);
});

it('keeps jobs as rows on the database connection and works them one at a time', function () {
    $journal = QueueApp::journal();
    $queue = $this->app['queue']->connection('database');

    $first = $queue->push(new RecordJob($journal, 'first'));
    $queue->push(new RecordJob($journal, 'second'), queue: 'other');
    $queue->later(60, new RecordJob($journal, 'later'));

    expect($first)->toBeInt()
        ->and($queue->size())->toBe(2)
        ->and($queue->pendingSize())->toBe(1)
        ->and($queue->delayedSize())->toBe(1)
        ->and($queue->size('other'))->toBe(1);

    [$code] = QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => 'database', '--once' => true]);

    expect($code)->toBe(0)
        ->and(journalLines())->toBe(['first'])
        ->and($queue->size())->toBe(1)
        ->and($queue->reservedSize())->toBe(0);
});

it('records a job that failed in failed_jobs, and hands the failure to the job', function () {
    $journal = QueueApp::journal();
    $heard = [];
    $this->app['signals']->listen(JobFailed::class, function (JobFailed $failed) use (&$heard) { $heard[] = $failed->exception->getMessage(); });

    $this->app['queue']->connection('database')->push(new FailingJob($journal));
    QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => 'database', '--once' => true]);

    $failed = $this->app['db']->connection('sqlite')->table('failed_jobs')->first();

    expect(QueueApp::lines())->toBe(['failed: this job always fails'])
        ->and($heard)->toBe(['this job always fails'])
        ->and($failed->connection)->toBe('database')
        ->and($failed->uuid)->toBeString()->not->toBeEmpty()
        ->and($failed->exception)->toContain('this job always fails')
        ->and($this->app['queue']->connection('database')->size())->toBe(0);
});

it('pops with SKIP LOCKED on the servers that have it, and works a job from each', function (string $connection) {
    $journal = QueueApp::journal();
    $table = DatabaseApp::table($this->app, $connection, fn (Blueprint $table) => [
        $table->bigIncrements('id'), $table->string('queue')->index(), $table->longText('payload'),
        $table->unsignedTinyInteger('attempts'), $table->unsignedInteger('reserved_at')->nullable(),
        $table->unsignedInteger('available_at'), $table->unsignedInteger('created_at'),
    ]);
    $this->app['config']->set("queue.connections.{$connection}-jobs", [
        'driver' => 'database', 'connection' => $connection, 'table' => $table, 'queue' => 'default', 'retry_after' => 90,
    ]);
    $queue = $this->app['queue']->connection("{$connection}-jobs");
    $lock = new ReflectionMethod($queue, 'getLockForPopping');

    $queue->push(new RecordJob($journal, $connection));
    QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => "{$connection}-jobs", '--once' => true]);

    expect($lock->invoke($queue))->toBe('FOR UPDATE SKIP LOCKED')
        ->and(journalLines())->toBe([$connection])
        ->and($queue->size())->toBe(0);
})->with(function () {
    foreach (['mysql', 'pgsql'] as $connection) {
        if (DatabaseApp::reachable($connection)) {
            yield $connection => [$connection];
        }
    }
});

it('pushes to redis and works the job', function () {
    $journal = QueueApp::journal();
    $queue = $this->app['queue']->connection('redis');

    $id = $queue->push(new RecordJob($journal, 'from redis'));
    [$code] = QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => 'redis', '--once' => true]);

    expect($id)->toBeString()
        ->and($code)->toBe(0)
        ->and(journalLines())->toBe(['from redis'])
        ->and($queue->size())->toBe(0);
})->skip(fn () => ! QueueApp::redisReachable(), 'needs the local Redis');

it('runs a background job in a pool worker and a deferred one on the next loop turn, here', function () {
    $journal = QueueApp::journal();
    $queues = $this->app['queue'];

    $background = $queues->connection('background')->push(new RecordJob($journal, 'background'));
    $deferred = $queues->connection('deferred')->push(new RecordJob($journal, 'deferred'));
    $before = QueueApp::lines();

    $background->wait();
    $deferred->wait();

    [$ran_background] = array_values(array_filter(QueueApp::lines(), fn ($line) => str_starts_with($line, 'background')));

    expect($before)->not->toContain('deferred @'.getmypid())
        ->and(QueueApp::lines())->toContain('deferred @'.getmypid())
        ->and($ran_background)->not->toBe('background @'.getmypid())
        ->and($this->app->get('process-workers')->workerCount())->toBeGreaterThan(0);
});

it('runs a batch, keeps its counts in job_batches, and calls its callbacks', function () {
    $journal = QueueApp::journal();

    $batch = $this->app->make(Dispatcher::class)
        ->batch([new BatchedJob($journal, 'one'), new BatchedJob($journal, 'two')])
        ->name('nightly')
        ->onConnection('database')
        ->then(journaling($journal, 'then'))
        ->finally(journaling($journal, 'finally'))
        ->dispatch();

    $pending = $batch->fresh()->pendingJobs;
    QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0]);
    $done = $batch->fresh();

    expect($pending)->toBe(2)
        ->and(QueueApp::lines())->toBe(['one in nightly', 'two in nightly', 'then', 'finally'])
        ->and($done->pendingJobs)->toBe(0)
        ->and($done->finished())->toBeTrue()
        ->and($this->app['db']->connection('sqlite')->table('job_batches')->where('id', $batch->id)->value('name'))->toBe('nightly');
});

it('pushes a unique job once until it has run', function () {
    $journal = QueueApp::journal();
    $queue = $this->app['queue']->connection('database');

    dispatch(new UniqueJob($journal, 'only once'))->onConnection('database');
    dispatch(new UniqueJob($journal, 'only once'))->onConnection('database');
    $queued = $queue->size();

    QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => 'database', '--once' => true]);
    dispatch(new UniqueJob($journal, 'only once'))->onConnection('database');

    expect($queued)->toBe(1)
        ->and(journalLines())->toBe(['only once'])
        ->and($queue->size())->toBe(1);
});

it('carries the log context a job was dispatched in to the worker', function () {
    $journal = QueueApp::journal();

    $this->app->make(Repository::class)->add('request_id', 'abc-123');
    dispatch(new ContextJob($journal))->onConnection('database');
    $payload = json_decode($this->app['db']->connection('sqlite')->table('jobs')->value('payload'), true);

    $this->app->make(Repository::class)->forget('request_id');
    QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => 'database', '--once' => true]);

    expect($payload)->toHaveKey('voyager:log:context')
        ->and(QueueApp::lines())->toBe(['context: {"request_id":"abc-123"}']);
});

it('writes the jobs, failed_jobs and job_batches migrations, once each', function () {
    $directory = sys_get_temp_dir().'/venusian-queue-tables-'.bin2hex(random_bytes(4));
    $this->app->useDatabasePath($directory);

    try {
        $codes = array_map(fn (string $command) => QueueApp::run($this->app, ['command' => $command])[0], [
            'queue:table', 'queue:failed-table', 'queue:batches-table', 'make:queue-table',
        ]);
        $written = array_map(fn (string $path) => preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($path)), glob($directory.'/migrations/*.php'));
        sort($written);

        expect($codes)->toBe([0, 0, 0, 1])
            ->and($written)->toBe(['create_failed_jobs_table.php', 'create_job_batches_table.php', 'create_jobs_table.php']);
    } finally {
        $this->app['files']->deleteDirectory($directory);
    }
});

it('pushes a unique queued listener once per unique id until it has run', function () {
    $journal = QueueApp::journal();
    $signals = $this->app['signals'];
    $signals->listen(Greeted::class, UniqueGreeting::class);

    $signals->dispatch(new Greeted($journal, 'ada'));
    $signals->dispatch(new Greeted($journal, 'ada'));
    $signals->dispatch(new Greeted($journal, 'grace'));
    $queued = $this->app['queue']->connection('database')->size();

    QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0]);

    expect($queued)->toBe(2)
        ->and(QueueApp::lines())->toBe(['unique greeting for ada', 'unique greeting for grace']);
});

it('gives a batched job a fake batch to run against in isolation', function () {
    $journal = QueueApp::journal();

    [$job, $batch] = new BatchedJob($journal, 'alone')->withFakeBatch(name: 'fake');
    $job->handle();
    $batch->add([new BatchedJob($journal, 'added')]);
    $batch->cancel();

    expect($batch)->toBeInstanceOf(BatchFake::class)
        ->and(QueueApp::lines())->toBe(['alone in fake'])
        ->and($batch->added)->toHaveCount(1)
        ->and($batch->totalJobs)->toBe(1)
        ->and($batch->cancelled())->toBeTrue();
});

it('calls the manager\'s before, after and failing hooks around the jobs a worker runs', function () {
    $journal = QueueApp::journal();
    $heard = [];
    $queues = $this->app['queue'];
    $queues->before(function ($event) use (&$heard) { $heard[] = 'before '.$event->job->resolveName(); });
    $queues->after(function ($event) use (&$heard) { $heard[] = 'after '.$event->job->resolveName(); });
    $queues->failing(function ($event) use (&$heard) { $heard[] = 'failing '.$event->job->resolveName(); });

    $queues->connection('database')->push(new RecordJob($journal, 'hooked'));
    $queues->connection('database')->push(new FailingJob($journal));
    QueueApp::run($this->app, ['command' => 'queue:work', 'connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0]);

    expect($heard)->toBe([
        'before '.RecordJob::class, 'after '.RecordJob::class,
        'before '.FailingJob::class, 'failing '.FailingJob::class,
    ]);
});
