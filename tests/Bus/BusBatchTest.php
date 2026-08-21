<?php

use Carbon\CarbonImmutable;
use Tests\Bus\Fixtures\ChainHeadJob;
use Tests\Bus\Fixtures\SecondTestJob;
use Tests\Bus\Fixtures\ThirdTestJob;
use Voyager\Bus\Batch;
use Voyager\Bus\Batchable;
use Voyager\Bus\BatchFactory;
use Voyager\Bus\DatabaseBatchRepository;
use Voyager\Bus\Dispatcher;
use Voyager\Bus\Events\BatchCanceled;
use Voyager\Bus\Events\BatchFinished;
use Voyager\Bus\PendingBatch;
use Voyager\Contracts\Bus\Dispatcher as BusDispatcher;
use Voyager\Contracts\Events\Dispatcher as EventDispatcher;
use Voyager\Contracts\Queue\Factory;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Instrument\Model;
use Voyager\Database\PostgresConnection;
use Voyager\Database\Query\Builder;
use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\MagicAliases\Bus;
use Voyager\NutsAndBolts\MagicAliases\Queue;
use Voyager\Queue\CallQueuedClosure;
use Voyager\System\Bus\PendingChain;
use Voyager\Vessel\Vessel;

function busBatchConnection()
{
    return Model::getConnectionResolver()->connection();
}

function busBatchSchema()
{
    return busBatchConnection()->getSchemaBuilder();
}

function busBatchCreateSchema()
{
    busBatchSchema()->create('job_batches', function ($table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->integer('total_jobs');
        $table->integer('pending_jobs');
        $table->integer('failed_jobs');
        $table->text('failed_job_ids');
        $table->text('options')->nullable();
        $table->integer('cancelled_at')->nullable();
        $table->integer('created_at');
        $table->integer('finished_at')->nullable();
    });
}

function busBatchCreateTestBatch($queue, $allowFailures = false)
{
    $repository = new DatabaseBatchRepository(new BatchFactory($queue), DB::connection(), 'job_batches');

    $pendingBatch = (new PendingBatch(new Vessel, collect()))
        ->progress(function (Batch $batch) {
            $_SERVER['__progress.batch'] = $batch;
            $_SERVER['__progress.count']++;
        })
        ->then(function (Batch $batch) {
            $_SERVER['__then.batch'] = $batch;
            $_SERVER['__then.count']++;
        })
        ->catch(function (Batch $batch, $e) {
            $_SERVER['__catch.batch'] = $batch;
            $_SERVER['__catch.exception'] = $e;
            $_SERVER['__catch.count']++;
        })
        ->finally(function (Batch $batch) {
            $_SERVER['__finally.batch'] = $batch;
            $_SERVER['__finally.count']++;
        })
        ->allowFailures($allowFailures)
        ->onConnection('test-connection')
        ->onQueue('test-queue');

    return $repository->store($pendingBatch);
}

beforeEach(function () {
    $db = new DB;

    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    $db->setAsGlobal();

    if (! MagicAlias::getMagicAliasApplication()) {
        $vessel = new Vessel;
        MagicAlias::setMagicAliasApplication($vessel);

        $queue = Mockery::mock(Factory::class);
        $vessel->instance(Factory::class, $queue);
        $vessel->alias(Factory::class, 'queue');

        $dispatcher = Mockery::mock(Dispatcher::class, [$vessel]);

        $dispatcher->shouldReceive('batch')->zeroOrMoreTimes()->andReturnUsing(function ($jobs) {
            $pendingBatch = Mockery::mock(PendingBatch::class);
            $pendingBatch->shouldReceive('name')->andReturnSelf();
            $pendingBatch->shouldReceive('dispatch')->zeroOrMoreTimes()->andReturn(Mockery::mock(Batch::class));

            return $pendingBatch;
        })->byDefault();

        $dispatcher->shouldReceive('chain')->zeroOrMoreTimes()->andReturnUsing(function ($jobs) {
            $pendingChain = Mockery::mock(PendingChain::class, [$jobs, [\stdClass::class]]);
            $pendingChain->shouldReceive('dispatch')->zeroOrMoreTimes()->andReturn(Mockery::mock(Batch::class));

            return $pendingChain;
        })->byDefault();

        $vessel->instance(BusDispatcher::class, $dispatcher);
        $vessel->alias(BusDispatcher::class, 'bus');
    }

    busBatchCreateSchema();

    $_SERVER['__finally.count'] = 0;
    $_SERVER['__progress.count'] = 0;
    $_SERVER['__then.count'] = 0;
    $_SERVER['__catch.count'] = 0;
});

afterEach(function () {
    if (MagicAlias::getMagicAliasApplication()) {
        MagicAlias::setMagicAliasApplication(null);
    }

    Vessel::setInstance(null);

    unset($_SERVER['__finally.batch'], $_SERVER['__progress.batch'], $_SERVER['__then.batch'], $_SERVER['__catch.batch'], $_SERVER['__catch.exception']);

    busBatchSchema()->drop('job_batches');
});

test('jobs can be added to the batch', function () {
        $queue = Mockery::mock(Factory::class);

        $batch = busBatchCreateTestBatch($queue);

        $job = new class
        {
            use Batchable;
        };

        $secondJob = new class
        {
            use Batchable;
        };

        $thirdJob = function () {
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = Mockery::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once()->with(Mockery::on(function ($args) use ($job, $secondJob) {
            return
                $args[0] == $job &&
                $args[1] == $secondJob &&
                $args[2] instanceof CallQueuedClosure
                && is_string($args[2]->batchId);
        }), '', 'test-queue');

        $batch = $batch->add([$job, $secondJob, $thirdJob]);

        expect($batch->totalJobs)->toEqual(3);
        expect($batch->pendingJobs)->toEqual(3);
        expect($job->batchId)->toBeString();
        expect($batch->createdAt)->toBeInstanceOf(CarbonImmutable::class);
    });

test('jobs can be added to pending batch', function () {
        $batch = new PendingBatch(new Vessel, collect());
        expect($batch->jobs)->toHaveCount(0);

        $job = new class
        {
            use Batchable;
        };
        $batch->add([$job]);
        expect($batch->jobs)->toHaveCount(1);

        $secondJob = new class
        {
            use Batchable;

            public $anotherProperty;
        };
        $batch->add($secondJob);
        expect($batch->jobs)->toHaveCount(2);
    });

test('jobs can be added to the pending batch from iterable', function () {
        $batch = new PendingBatch(new Vessel, collect());
        expect($batch->jobs)->toHaveCount(0);

        $count = 3;
        $generator = function (int $jobsCount) {
            for ($i = 0; $i < $jobsCount; $i++) {
                yield new class
                {
                    use Batchable;
                };
            }
        };

        $batch->add($generator($count));
        expect($batch->jobs)->toHaveCount($count);
    });

test('processed jobs can be calculated', function () {
        $queue = Mockery::mock(Factory::class);

        $batch = busBatchCreateTestBatch($queue);

        $batch->totalJobs = 10;
        $batch->pendingJobs = 4;

        expect($batch->processedJobs())->toEqual(6);
        expect($batch->progress())->toEqual(60);
    });

test('successful jobs can be recorded', function () {
        $queue = Mockery::mock(Factory::class);

        $batch = busBatchCreateTestBatch($queue);

        $job = new class
        {
            use Batchable;
        };

        $secondJob = new class
        {
            use Batchable;
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = Mockery::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once();

        $batch = $batch->add([$job, $secondJob]);
        expect($batch->pendingJobs)->toEqual(2);

        $batch->recordSuccessfulJob('test-id');
        $batch->recordSuccessfulJob('test-id');

        expect($_SERVER['__finally.batch'])->toBeInstanceOf(Batch::class);
        expect($_SERVER['__progress.batch'])->toBeInstanceOf(Batch::class);
        expect($_SERVER['__then.batch'])->toBeInstanceOf(Batch::class);

        $batch = $batch->fresh();
        expect($batch->pendingJobs)->toEqual(0);
        expect($batch->finished())->toBeTrue();
        expect($_SERVER['__finally.count'])->toEqual(1);
        expect($_SERVER['__progress.count'])->toEqual(2);
        expect($_SERVER['__then.count'])->toEqual(1);
    });

test('batch finished event is dispatched', function () {
        Vessel::getInstance()->instance(EventDispatcher::class, $events = Mockery::mock(EventDispatcher::class));

        $queue = Mockery::mock(Factory::class);
        $batch = busBatchCreateTestBatch($queue);

        $job = new class
        {
            use Batchable;
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = Mockery::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once();

        $batch = $batch->add([$job]);

        $events->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) use ($batch) {
            return $event instanceof BatchFinished && $event->batch === $batch;
        }));

        $batch->recordSuccessfulJob('test-id');
    });

test('failed jobs can be recorded while not allowing failures', function () {
        $queue = Mockery::mock(Factory::class);

        $batch = busBatchCreateTestBatch($queue, $allowFailures = false);

        $job = new class
        {
            use Batchable;
        };

        $secondJob = new class
        {
            use Batchable;
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = Mockery::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once();

        $batch = $batch->add([$job, $secondJob]);
        expect($batch->pendingJobs)->toEqual(2);

        $batch->recordFailedJob('test-id', new RuntimeException('Something went wrong.'));
        $batch->recordFailedJob('test-id', new RuntimeException('Something else went wrong.'));

        expect($_SERVER['__finally.batch'])->toBeInstanceOf(Batch::class);
        expect(isset($_SERVER['__then.batch']))->toBeFalse();

        $batch = $batch->fresh();
        expect($batch->pendingJobs)->toEqual(2);
        expect($batch->failedJobs)->toEqual(2);
        expect($batch->finished())->toBeTrue();
        expect($batch->cancelled())->toBeTrue();
        expect($_SERVER['__finally.count'])->toEqual(1);
        expect($_SERVER['__progress.count'])->toEqual(0);
        expect($_SERVER['__catch.count'])->toEqual(1);
        expect($_SERVER['__catch.exception']->getMessage())->toBe('Something went wrong.');
    });

test('failed jobs can be recorded while allowing failures', function () {
        $queue = Mockery::mock(Factory::class);

        $batch = busBatchCreateTestBatch($queue, $allowFailures = true);

        $job = new class
        {
            use Batchable;
        };

        $secondJob = new class
        {
            use Batchable;
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = Mockery::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once();

        $batch = $batch->add([$job, $secondJob]);
        expect($batch->pendingJobs)->toEqual(2);

        $batch->recordFailedJob('test-id', new RuntimeException('Something went wrong.'));
        $batch->recordFailedJob('test-id', new RuntimeException('Something else went wrong.'));

        // While allowing failures this batch never actually completes...
        expect(isset($_SERVER['__then.batch']))->toBeFalse();

        $batch = $batch->fresh();
        expect($batch->pendingJobs)->toEqual(2);
        expect($batch->failedJobs)->toEqual(2);
        expect($batch->finished())->toBeFalse();
        expect($batch->cancelled())->toBeFalse();
        expect($_SERVER['__catch.count'])->toEqual(1);
        expect($_SERVER['__progress.count'])->toEqual(2);
        expect($_SERVER['__catch.exception']->getMessage())->toBe('Something went wrong.');
    });

test('pending batch filters out falsy jobs', function () {
        $job = new class
        {
            use Batchable;
        };

        $secondJob = new class
        {
            use Batchable;
        };

        $jobsWithNulls = collect([$job, null, $secondJob, [], 0, '', false]);

        $batch = new PendingBatch(new Vessel, $jobsWithNulls);

        expect($batch->jobs)->toHaveCount(2);
        expect($batch->jobs->contains($job))->toBeTrue();
        expect($batch->jobs->contains($secondJob))->toBeTrue();
    });

test('failure callbacks execute correctly', function () {
        $queue = Mockery::mock(Factory::class);

        $repository = new DatabaseBatchRepository(new BatchFactory($queue), DB::connection(), 'job_batches');

        $pendingBatch = (new PendingBatch(new Vessel, collect()))
            ->allowFailures([
                static fn (Batch $batch, $e): true => $_SERVER['__failure1.invoked'] = true,
                function (Batch $batch, $e) {
                    $_SERVER['__failure2.invoked'] = true;
                },
                function (Batch $batch, $e) {
                    $_SERVER['__failure3.batch'] = $batch;
                    $_SERVER['__failure3.exception'] = $e;
                    $_SERVER['__failure3.batch_id'] = $batch->id;
                    $_SERVER['__failure3.batch_class'] = get_class($batch);
                    $_SERVER['__failure3.exception_class'] = get_class($e);
                    $_SERVER['__failure3.exception_message'] = $e->getMessage();
                    $_SERVER['__failure3.param_count'] = func_num_args();
                },
            ])
            ->onConnection('test-connection')
            ->onQueue('test-queue');

        $batch = $repository->store($pendingBatch);

        $job = new class
        {
            use Batchable;
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = Mockery::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once();

        $batch = $batch->add([$job]);

        $_SERVER['__failure1.invoked'] = false;
        $_SERVER['__failure2.invoked'] = false;
        $_SERVER['__failure3.batch'] = null;
        $_SERVER['__failure3.exception'] = null;

        $batch->recordFailedJob('test-id', new RuntimeException('Comprehensive callback test.'));

        expect($_SERVER['__failure1.invoked'])->toBeTrue();
        expect($_SERVER['__failure2.invoked'])->toBeTrue();
        expect($_SERVER['__failure3.batch'])->toBeInstanceOf(Batch::class);
        expect($_SERVER['__failure3.exception']->getMessage())->toBe('Comprehensive callback test.');
        expect($_SERVER['__failure3.batch_id'])->toBe($batch->id);
        expect($_SERVER['__failure3.batch_class'])->toBe(Batch::class);
        expect($_SERVER['__failure3.exception_class'])->toBe(RuntimeException::class);
        expect($_SERVER['__failure3.param_count'])->toEqual(2);
    });

test('batch can be cancelled', function () {
        $queue = Mockery::mock(Factory::class);

        $batch = busBatchCreateTestBatch($queue);

        $batch->cancel();

        $batch = $batch->fresh();

        expect($batch->cancelled())->toBeTrue();
    });

test('batch cancelled event is dispatched', function () {
        Vessel::getInstance()->instance(EventDispatcher::class, $events = Mockery::mock(EventDispatcher::class));

        $queue = Mockery::mock(Factory::class);
        $batch = busBatchCreateTestBatch($queue);

        $events->shouldReceive('dispatch')->once()->with(Mockery::on(function ($event) use ($batch) {
            return $event instanceof BatchCanceled && $event->batch->id === $batch->id;
        }));

        $batch->cancel();
    });

test('batch can be deleted', function () {
        $queue = Mockery::mock(Factory::class);

        $batch = busBatchCreateTestBatch($queue);

        $batch->delete();

        $batch = $batch->fresh();

        expect($batch)->toBeNull();
    });

test('batch state can be inspected', function () {
        $queue = Mockery::mock(Factory::class);

        $batch = busBatchCreateTestBatch($queue);

        expect($batch->finished())->toBeFalse();
        $batch->finishedAt = now();
        expect($batch->finished())->toBeTrue();

        $batch->options['progress'] = [];
        expect($batch->hasProgressCallbacks())->toBeFalse();
        $batch->options['progress'] = [1];
        expect($batch->hasProgressCallbacks())->toBeTrue();

        $batch->options['then'] = [];
        expect($batch->hasThenCallbacks())->toBeFalse();
        $batch->options['then'] = [1];
        expect($batch->hasThenCallbacks())->toBeTrue();

        expect($batch->allowsFailures())->toBeFalse();
        $batch->options['allowFailures'] = true;
        expect($batch->allowsFailures())->toBeTrue();

        expect($batch->hasFailures())->toBeFalse();
        $batch->failedJobs = 1;
        expect($batch->hasFailures())->toBeTrue();

        $batch->options['catch'] = [];
        expect($batch->hasCatchCallbacks())->toBeFalse();
        $batch->options['catch'] = [1];
        expect($batch->hasCatchCallbacks())->toBeTrue();

        expect($batch->cancelled())->toBeFalse();
        $batch->cancelledAt = now();
        expect($batch->cancelled())->toBeTrue();

        expect(json_encode($batch))->toBeString();
    });

test('chain can be added to batch', function () {
        $queue = Mockery::mock(Factory::class);

        $batch = busBatchCreateTestBatch($queue);

        $chainHeadJob = new ChainHeadJob;

        $secondJob = new SecondTestJob;

        $thirdJob = new ThirdTestJob;

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = Mockery::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once()->with(Mockery::on(function ($args) use ($chainHeadJob, $secondJob, $thirdJob) {
            return
                $args[0] == $chainHeadJob
                && serialize($secondJob) == $args[0]->chained[0]
                && serialize($thirdJob) == $args[0]->chained[1];
        }), '', 'test-queue');

        $batch = $batch->add([
            [$chainHeadJob, $secondJob, $thirdJob],
        ]);

        expect($batch->totalJobs)->toEqual(3);
        expect($batch->pendingJobs)->toEqual(3);
        expect($chainHeadJob->chainQueue)->toBe('test-queue');
        expect($chainHeadJob->batchId)->toBeString();
        expect($secondJob->batchId)->toBeString();
        expect($thirdJob->batchId)->toBeString();
        expect($batch->createdAt)->toBeInstanceOf(CarbonImmutable::class);
    });

test('chained closure after multiple batches is properly dispatched', function () {
        Queue::fake();

        $TestBatchJob = new class
        {
            use Batchable;

            public function handle()
            {
            }
        };

        Bus::chain([
            Bus::batch([$TestBatchJob])->name('Batch 1'),
            Bus::batch([$TestBatchJob])->name('Batch 2'),
            function () {
            },
        ])->dispatch();

        expect(true)->toBeTrue();
    });

test('options serialization on postgres', function () {
        $pendingBatch = (new PendingBatch(new Vessel, collect()))
            ->onQueue('test-queue');

        $connection = Mockery::spy(PostgresConnection::class);
        $builder = Mockery::spy(Builder::class);

        $connection->shouldReceive('table')->andReturn($builder);
        $builder->shouldReceive('useWritePdo')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();

        $repository = new DatabaseBatchRepository(
            new BatchFactory(Mockery::mock(Factory::class)), $connection, 'job_batches'
        );

        $repository->store($pendingBatch);

        $builder->shouldHaveReceived('insert')
            ->withArgs(function ($argument) use ($pendingBatch) {
                return unserialize(base64_decode($argument['options'])) === $pendingBatch->options;
            });

        $builder->shouldHaveReceived('first');
    });

test('options unserialize on postgres', function ($serialize, $options) {
        $factory = Mockery::mock(BatchFactory::class);

        $connection = Mockery::spy(PostgresConnection::class);

        $connection->shouldReceive('table->useWritePdo->where->first')
            ->andReturn($m = (object) [
                'id' => '',
                'name' => '',
                'total_jobs' => '',
                'pending_jobs' => '',
                'failed_jobs' => '',
                'failed_job_ids' => '[]',
                'options' => $serialize,
                'created_at' => now()->timestamp,
                'cancelled_at' => null,
                'finished_at' => null,
            ]);

        $batch = (new DatabaseBatchRepository($factory, $connection, 'job_batches'));

        $factory->shouldReceive('make')
            ->withSomeOfArgs($batch, '', '', '', '', '', '', $options);

        $batch->find(1);
    })->with('serializedOptions');

dataset('serializedOptions', function () {
        $options = [1, 2];

        return [
            [serialize($options), $options],
            [base64_encode(serialize($options)), $options],
        ];
    });

