<?php

use Voyager\Vessel\Vessel;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Contracts\Queue\Job as QueueJobContract;
use Voyager\Queue\Events\JobExceptionOccurred;
use Voyager\Queue\Events\JobPopped;
use Voyager\Queue\Events\JobPopping;
use Voyager\Queue\Events\JobProcessed;
use Voyager\Queue\Events\JobProcessing;
use Voyager\Queue\Events\JobReleasedAfterException;
use Voyager\Queue\Events\WorkerStarting;
use Voyager\Queue\Events\WorkerStopping;
use Voyager\Queue\MaxAttemptsExceededException;
use Voyager\Queue\QueueManager;
use Voyager\Queue\Worker;
use Voyager\Queue\WorkerOptions;
use Voyager\Queue\WorkerStopReason;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mockery as m;

/**
 * Helpers...
 */
function queueWorkerGetWorker($connectionName = 'default', $jobs = [], ?callable $isInMaintenanceMode = null)
{
    return new InsomniacWorker(
        ...queueWorkerDependencies($connectionName, $jobs, $isInMaintenanceMode)
    );
}

function queueWorkerDependencies($connectionName = 'default', $jobs = [], ?callable $isInMaintenanceMode = null)
{
    return [
        new WorkerFakeManager($connectionName, new WorkerFakeConnection($connectionName, $jobs)),
        test()->events,
        test()->exceptionHandler,
        $isInMaintenanceMode ?? function () {
            return false;
        },
    ];
}

function queueWorkerOptions(array $overrides = [])
{
    $options = new WorkerOptions;

    foreach ($overrides as $key => $value) {
        $options->{$key} = $value;
    }

    return $options;
}

beforeEach(function () {
    $this->events = m::spy(Dispatcher::class);
    $this->exceptionHandler = m::spy(ExceptionHandler::class);

    Vessel::setInstance($container = new Vessel);

    $container->instance(Dispatcher::class, $this->events);
    $container->instance(ExceptionHandler::class, $this->exceptionHandler);
});

afterEach(function () {
    Carbon::setTestNow();

    Vessel::setInstance();
});

test('job can be fired', function () {
    $worker = queueWorkerGetWorker('default', ['queue' => [$job = new WorkerFakeJob]]);
    $worker->runNextJob('default', 'queue', new WorkerOptions);
    expect($job->fired)->toBeTrue();
    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobPopping::class))->once();
    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobPopped::class))->once();
    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessing::class))->once();
    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessed::class))->once();
});

test('job popping event', function () {
    $worker = queueWorkerGetWorker('default', ['queue' => [$job = new WorkerFakeJob]]);
    $worker->runNextJob('default', 'queue', new WorkerOptions);
    expect($job->fired)->toBeTrue();

    $this->events->shouldHaveReceived('dispatch')->with(m::on(function ($event) {
        return $event instanceof JobPopping
            && $event->connectionName === 'default'
            && $event->queue === 'queue';
    }))->once();
});

test('worker can work until queue is empty', function () {
    $workerOptions = new WorkerOptions;
    $workerOptions->stopWhenEmpty = true;

    $worker = queueWorkerGetWorker('default', ['queue' => [
        $firstJob = new WorkerFakeJob,
        $secondJob = new WorkerFakeJob,
    ]]);

    $status = $worker->daemon('default', 'queue', $workerOptions);

    expect($secondJob->fired)->toBeTrue()
        ->and($status)->toBe(0);

    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessing::class))->twice();

    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessed::class))->twice();
});

test('worker stops when queue is empty for configured seconds', function () {
    $workerOptions = new WorkerOptions();
    $workerOptions->stopWhenEmptyFor = 5;

    $worker = queueWorkerGetWorker('default', ['queue' => []]);
    $worker->currentTime = 0;

    $status = $worker->daemon('default', 'queue', $workerOptions);

    expect($status)->toBe(0);

    $this->events->shouldHaveReceived('dispatch')->with(m::on(function ($event) use ($workerOptions) {
        return $event instanceof WorkerStopping
            && $event->status === 0
            && $event->workerOptions === $workerOptions
            && $event->reason === WorkerStopReason::QueueEmptyFor;
    }))->once();
});

test('worker resets queue empty timer after processing job', function () {
    $workerOptions = new WorkerOptions();
    $workerOptions->stopWhenEmptyFor = 5;

    $worker = queueWorkerGetWorker('default', ['queue' => [
        $job = new WorkerFakeJob(function () use (&$worker) {
            $worker->currentTime = 10;
        }),
    ]]);
    $worker->currentTime = 0;

    $status = $worker->daemon('default', 'queue', $workerOptions);

    expect($job->fired)->toBeTrue()
        ->and($status)->toBe(0)
        ->and($worker->currentTime)->toBe(16);

    $this->events->shouldHaveReceived('dispatch')->with(m::on(function ($event) use ($workerOptions) {
        return $event instanceof WorkerStopping
            && $event->status === 0
            && $event->workerOptions === $workerOptions
            && $event->reason === WorkerStopReason::QueueEmptyFor;
    }))->once();
});

test('worker stops when memory exceeded', function () {
    $workerOptions = new WorkerOptions;

    $worker = queueWorkerGetWorker('default', ['queue' => [
        $firstJob = new WorkerFakeJob,
        $secondJob = new WorkerFakeJob,
    ]]);
    $worker->stopOnMemoryExceeded = true;

    $status = $worker->daemon('default', 'queue', $workerOptions);

    expect($firstJob->fired)->toBeTrue()
        ->and($secondJob->fired)->toBeFalse()
        ->and($status)->toBe(12);

    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessing::class))->once();

    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessed::class))->once();
});

test('worker memory exceeded when memory is zero', function () {
    $worker = new Worker(...queueWorkerDependencies());
    expect($worker->memoryExceeded(0))->toBeFalse();
});

test('worker memory exceeded when memory greater than zero', function () {
    $worker = new Worker(...queueWorkerDependencies());
    expect($worker->memoryExceeded(1))->toBeTrue();
});

test('worker memory exceeded when memory is negative', function () {
    $worker = new Worker(...queueWorkerDependencies());
    expect($worker->memoryExceeded(-1))->toBeFalse();
});

test('job can be fired based on priority', function () {
    $worker = queueWorkerGetWorker('default', [
        'high' => [$highJob = new WorkerFakeJob, $secondHighJob = new WorkerFakeJob], 'low' => [$lowJob = new WorkerFakeJob],
    ]);

    $worker->runNextJob('default', 'high,low', new WorkerOptions);
    expect($highJob->fired)->toBeTrue()
        ->and($secondHighJob->fired)->toBeFalse()
        ->and($lowJob->fired)->toBeFalse();

    $worker->runNextJob('default', 'high,low', new WorkerOptions);
    expect($secondHighJob->fired)->toBeTrue()
        ->and($lowJob->fired)->toBeFalse();

    $worker->runNextJob('default', 'high,low', new WorkerOptions);
    expect($lowJob->fired)->toBeTrue();
});

test('exception is reported if connection throws exception on job pop', function () {
    $worker = new InsomniacWorker(
        new WorkerFakeManager('default', new BrokenQueueConnection('default', $e = new RuntimeException)),
        $this->events,
        $this->exceptionHandler,
        function () {
            return false;
        }
    );

    $worker->runNextJob('default', 'queue', queueWorkerOptions());

    $this->exceptionHandler->shouldHaveReceived('report')->with($e);
});

test('worker sleeps when queue is empty', function () {
    $worker = queueWorkerGetWorker('default', ['queue' => []]);
    $worker->runNextJob('default', 'queue', queueWorkerOptions(['sleep' => 5]));
    expect($worker->sleptFor)->toEqual(5);
});

test('job is released on exception', function () {
    $e = new RuntimeException;

    $job = new WorkerFakeJob(function () use ($e) {
        throw $e;
    });

    $worker = queueWorkerGetWorker('default', ['queue' => [$job]]);
    $worker->runNextJob('default', 'queue', queueWorkerOptions(['backoff' => 10]));

    expect($job->releaseAfter)->toEqual(10)
        ->and($job->deleted)->toBeFalse();
    $this->exceptionHandler->shouldHaveReceived('report')->with($e);
    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobExceptionOccurred::class))->once();
    $this->events->shouldNotHaveReceived('dispatch', [m::type(JobProcessed::class)]);
});

test('job is not released if it has exceeded max attempts', function () {
    $e = new RuntimeException;

    $job = new WorkerFakeJob(function ($job) use ($e) {
        // In normal use this would be incremented by being popped off the queue
        $job->attempts++;

        throw $e;
    });
    $job->attempts = 1;

    $worker = queueWorkerGetWorker('default', ['queue' => [$job]]);
    $worker->runNextJob('default', 'queue', queueWorkerOptions(['maxTries' => 1]));

    expect($job->releaseAfter)->toBeNull()
        ->and($job->deleted)->toBeTrue()
        ->and($job->failedWith)->toEqual($e);
    $this->exceptionHandler->shouldHaveReceived('report')->with($e);
    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobExceptionOccurred::class))->once();
    $this->events->shouldNotHaveReceived('dispatch', [m::type(JobProcessed::class)]);
});

test('job is not released if it has expired', function () {
    $e = new RuntimeException;

    $job = new WorkerFakeJob(function ($job) use ($e) {
        // In normal use this would be incremented by being popped off the queue
        $job->attempts++;

        throw $e;
    });

    $job->retryUntil = now()->addSeconds(1)->getTimestamp();

    $job->attempts = 0;

    Carbon::setTestNow(
        Carbon::now()->addSeconds(1)
    );

    $worker = queueWorkerGetWorker('default', ['queue' => [$job]]);
    $worker->runNextJob('default', 'queue', queueWorkerOptions());

    expect($job->releaseAfter)->toBeNull()
        ->and($job->deleted)->toBeTrue()
        ->and($job->failedWith)->toEqual($e);
    $this->exceptionHandler->shouldHaveReceived('report')->with($e);
    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobExceptionOccurred::class))->once();
    $this->events->shouldNotHaveReceived('dispatch', [m::type(JobProcessed::class)]);
});

test('job is failed if it has already exceeded max attempts', function () {
    $job = new WorkerFakeJob(function ($job) {
        $job->attempts++;
    });

    $job->attempts = 2;

    $worker = queueWorkerGetWorker('default', ['queue' => [$job]]);
    $worker->runNextJob('default', 'queue', queueWorkerOptions(['maxTries' => 1]));

    expect($job->releaseAfter)->toBeNull()
        ->and($job->deleted)->toBeTrue()
        ->and($job->failedWith)->toBeInstanceOf(MaxAttemptsExceededException::class);
    $this->exceptionHandler->shouldHaveReceived('report')->with(m::type(MaxAttemptsExceededException::class));
    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobExceptionOccurred::class))->once();
    $this->events->shouldNotHaveReceived('dispatch', [m::type(JobProcessed::class)]);
});

test('job is failed if it has already expired', function () {
    $job = new WorkerFakeJob(function ($job) {
        $job->attempts++;
    });

    $job->retryUntil = Carbon::now()->addSeconds(2)->getTimestamp();

    $job->attempts = 1;

    Carbon::setTestNow(
        Carbon::now()->addSeconds(3)
    );

    $worker = queueWorkerGetWorker('default', ['queue' => [$job]]);
    $worker->runNextJob('default', 'queue', queueWorkerOptions());

    expect($job->releaseAfter)->toBeNull()
        ->and($job->deleted)->toBeTrue()
        ->and($job->failedWith)->toBeInstanceOf(MaxAttemptsExceededException::class);
    $this->exceptionHandler->shouldHaveReceived('report')->with(m::type(MaxAttemptsExceededException::class));
    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobExceptionOccurred::class))->once();
    $this->events->shouldNotHaveReceived('dispatch', [m::type(JobProcessed::class)]);
});

test('job based max retries', function () {
    $job = new WorkerFakeJob(function ($job) {
        $job->attempts++;
    });
    $job->attempts = 2;

    $job->maxTries = 10;

    $worker = queueWorkerGetWorker('default', ['queue' => [$job]]);
    $worker->runNextJob('default', 'queue', queueWorkerOptions(['maxTries' => 1]));

    expect($job->deleted)->toBeFalse()
        ->and($job->failedWith)->toBeNull();
});

test('job based failed delay', function () {
    $job = new WorkerFakeJob(function ($job) {
        throw new Exception('Something went wrong.');
    });

    $job->attempts = 1;
    $job->backoff = 10;

    $worker = queueWorkerGetWorker('default', ['queue' => [$job]]);
    $worker->runNextJob('default', 'queue', queueWorkerOptions(['backoff' => 3, 'maxTries' => 0]));

    expect($job->releaseAfter)->toEqual(10);
});

test('job runs if app is not in maintenance mode', function () {
    $firstJob = new WorkerFakeJob(function ($job) {
        $job->attempts++;
    });

    $secondJob = new WorkerFakeJob(function ($job) {
        $job->attempts++;
    });

    $this->maintenanceFlags = [false, true];

    $maintenanceModeChecker = function () {
        if ($this->maintenanceFlags) {
            return array_shift($this->maintenanceFlags);
        }

        throw new LoopBreakerException;
    };

    $worker = queueWorkerGetWorker('default', ['queue' => [$firstJob, $secondJob]], $maintenanceModeChecker);

    try {
        $worker->daemon('default', 'queue', queueWorkerOptions());

        $this->fail('Expected LoopBreakerException to be thrown');
    } catch (LoopBreakerException) {
        expect($firstJob->attempts)->toBe(1)
            ->and($secondJob->attempts)->toBe(0);
    }
});

test('job does not fire if deleted', function () {
    $job = new WorkerFakeJob(function () {
        return true;
    });

    $worker = queueWorkerGetWorker('default', ['queue' => [$job]]);
    $job->delete();
    $worker->runNextJob('default', 'queue', queueWorkerOptions());

    $this->events->shouldHaveReceived('dispatch')->with(m::type(JobProcessed::class))->once();
    expect($job->hasFailed())->toBeFalse()
        ->and($job->isReleased())->toBeFalse()
        ->and($job->isDeleted())->toBeTrue();
});

test('worker picks job using custom callbacks', function () {
    $worker = queueWorkerGetWorker('default', [
        'default' => [$defaultJob = new WorkerFakeJob], 'custom' => [$customJob = new WorkerFakeJob],
    ]);

    $worker->runNextJob('default', 'default', new WorkerOptions);
    $worker->runNextJob('default', 'default', new WorkerOptions);

    expect($defaultJob->fired)->toBeTrue()
        ->and($customJob->fired)->toBeFalse();

    $worker2 = queueWorkerGetWorker('default', [
        'default' => [$defaultJob = new WorkerFakeJob], 'custom' => [$customJob = new WorkerFakeJob],
    ]);

    $worker2->setName('myworker');

    Worker::popUsing('myworker', function ($pop) {
        return $pop('custom');
    });

    $worker2->runNextJob('default', 'default', new WorkerOptions);
    $worker2->runNextJob('default', 'default', new WorkerOptions);

    expect($defaultJob->fired)->toBeFalse()
        ->and($customJob->fired)->toBeTrue();

    Worker::popUsing('myworker', null);
});

test('worker starting is dispatched', function () {
    $workerOptions = new WorkerOptions();
    $workerOptions->stopWhenEmpty = true;

    $worker = queueWorkerGetWorker('default', ['queue' => [
        $firstJob = new WorkerFakeJob(),
        $secondJob = new WorkerFakeJob(),
    ]]);

    $worker->daemon('default', 'queue', $workerOptions);

    expect($firstJob->fired)->toBeTrue()
        ->and($secondJob->fired)->toBeTrue();

    $this->events->shouldHaveReceived('dispatch')->with(m::type(WorkerStarting::class))->once();
});

test('worker stopping is dispatched', function () {
    $workerOptions = new WorkerOptions();
    $workerOptions->stopWhenEmpty = true;

    $worker = queueWorkerGetWorker('default', ['queue' => [
        $firstJob = new WorkerFakeJob(),
        $secondJob = new WorkerFakeJob(),
    ]]);

    $worker->daemon('default', 'queue', $workerOptions);

    expect($firstJob->fired)->toBeTrue()
        ->and($secondJob->fired)->toBeTrue();

    $this->events->shouldHaveReceived('dispatch')->with(m::on(function ($event) use ($workerOptions) {
        return $event instanceof WorkerStopping
            && $event->status === 0
            && $event->workerOptions === $workerOptions
            && $event->reason === WorkerStopReason::QueueEmpty;
    }))->once();
});

test('worker stops with lost connection reason', function () {
    $workerOptions = new WorkerOptions();
    $workerOptions->stopWhenEmpty = true;

    $worker = queueWorkerGetWorker('default', ['queue' => [
        $job = new WorkerFakeJob(function () {
            throw new RuntimeException('server has gone away');
        }),
    ]]);

    $worker->daemon('default', 'queue', $workerOptions);

    expect($job->fired)->toBeTrue();

    $this->events->shouldHaveReceived('dispatch')->with(m::on(function ($event) use ($workerOptions) {
        return $event instanceof WorkerStopping
            && $event->status === 0
            && $event->workerOptions === $workerOptions
            && $event->reason === WorkerStopReason::LostConnection;
    }));
});

test('job released event', function () {
    $e = new RuntimeException;

    $job = new WorkerFakeJob(function () use ($e) {
        throw $e;
    });

    $worker = queueWorkerGetWorker('default', ['queue' => [$job]]);
    $worker->runNextJob('default', 'queue', queueWorkerOptions(['backoff' => 10]));

    $this->events->shouldHaveReceived('dispatch')->with(m::on(function ($event) use ($job) {
        return $event instanceof JobReleasedAfterException
            && $event->connectionName === 'default'
            && $event->job === $job
            && $event->backoff === 10;
    }))->once();
});

/**
 * Fakes.
 */
class InsomniacWorker extends Worker
{
    public $sleptFor;
    public $stopOnMemoryExceeded = false;
    public $currentTime;

    public function sleep($seconds)
    {
        $this->sleptFor = $seconds;

        if (! is_null($this->currentTime)) {
            $this->currentTime += $seconds;
        }
    }

    protected function currentTime()
    {
        return $this->currentTime ?? parent::currentTime();
    }

    public function stop($status = 0, $options = null, $reason = null)
    {
        return parent::stop($status, $options, $reason);
    }

    public function daemonShouldRun(WorkerOptions $options, $connectionName, $queue)
    {
        return ! ($this->isDownForMaintenance)();
    }

    public function memoryExceeded($memoryLimit)
    {
        return $this->stopOnMemoryExceeded;
    }
}

class WorkerFakeManager extends QueueManager
{
    public $connections = [];

    public function __construct($name, $connection)
    {
        $this->connections[$name] = $connection;
    }

    public function connection($name = null)
    {
        return $this->connections[$name];
    }
}

class WorkerFakeConnection
{
    public $connectionName;
    public $jobs = [];

    public function __construct($connectionName, $jobs)
    {
        $this->connectionName = $connectionName;
        $this->jobs = $jobs;
    }

    public function pop($queue)
    {
        return array_shift($this->jobs[$queue]);
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }
}

class BrokenQueueConnection
{
    public $connectionName;
    public $exception;

    public function __construct($connectionName, $exception)
    {
        $this->connectionName = $connectionName;
        $this->exception = $exception;
    }

    public function pop($queue)
    {
        throw $this->exception;
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }
}

class WorkerFakeJob implements QueueJobContract
{
    public $id = '';
    public $fired = false;
    public $callback;
    public $deleted = false;
    public $releaseAfter;
    public $released = false;
    public $maxTries;
    public $maxExceptions;
    public $shouldFailOnTimeout = false;
    public $uuid;
    public $backoff;
    public $retryUntil;
    public $attempts = 0;
    public $failedWith;
    public $failed = false;
    public $connectionName = '';
    public $queue = '';
    public $rawBody = '';

    public function __construct($callback = null)
    {
        $this->callback = $callback ?: function () {
            //
        };
    }

    public function getJobId()
    {
        return $this->id;
    }

    public function fire()
    {
        $this->fired = true;
        $this->callback->__invoke($this);
    }

    public function payload()
    {
        return [];
    }

    public function maxTries()
    {
        return $this->maxTries;
    }

    public function maxExceptions()
    {
        return $this->maxExceptions;
    }

    public function shouldFailOnTimeout()
    {
        return $this->shouldFailOnTimeout;
    }

    public function uuid()
    {
        return $this->uuid;
    }

    public function backoff()
    {
        return $this->backoff;
    }

    public function retryUntil()
    {
        return $this->retryUntil;
    }

    public function delete()
    {
        $this->deleted = true;
    }

    public function isDeleted()
    {
        return $this->deleted;
    }

    public function release($delay = 0)
    {
        $this->released = true;

        $this->releaseAfter = $delay;
    }

    public function isReleased()
    {
        return $this->released;
    }

    public function isDeletedOrReleased()
    {
        return $this->deleted || $this->released;
    }

    public function attempts()
    {
        return $this->attempts;
    }

    public function markAsFailed()
    {
        $this->failed = true;
    }

    public function fail($e = null)
    {
        $this->markAsFailed();

        $this->delete();

        $this->failedWith = $e;
    }

    public function hasFailed()
    {
        return $this->failed;
    }

    public function getName()
    {
        return 'WorkerFakeJob';
    }

    public function resolveName()
    {
        return $this->getName();
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function getRawBody()
    {
        return $this->rawBody;
    }

    public function timeout()
    {
        return time() + 60;
    }

    public function resolveQueuedJobClass()
    {
        return 'WorkerFakeJob';
    }
}

class LoopBreakerException extends RuntimeException
{
    //
}
