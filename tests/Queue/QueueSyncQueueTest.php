<?php

use Voyager\Vessel\Vessel;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Contracts\Queue\QueueableEntity;
use Voyager\Contracts\Queue\ShouldBeUnique;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Contracts\Queue\ShouldQueueAfterCommit;
use Voyager\Queue\InteractsWithQueue;
use Voyager\Queue\Jobs\SyncJob;
use Voyager\Queue\SyncQueue;
use Mockery as m;

afterEach(function () {
    Vessel::setInstance(null);
});

test('push should fire job instantly', function () {
    unset($_SERVER['__sync.test']);

    $sync = new SyncQueue;
    $container = new Vessel;
    $sync->setContainer($container);

    $sync->push(SyncQueueTestHandler::class, ['foo' => 'bar']);
    expect($_SERVER['__sync.test'][0])->toBeInstanceOf(SyncJob::class);
    expect($_SERVER['__sync.test'][1])->toBe(['foo' => 'bar']);
});

test('failed job gets handled when an exception is thrown', function () {
    unset($_SERVER['__sync.failed']);

    $sync = new SyncQueue;
    $container = new Vessel;
    Vessel::setInstance($container);
    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->times(4);
    $container->instance('events', $events);
    $container->instance(Dispatcher::class, $events);
    $sync->setContainer($container);

    try {
        $sync->push(FailingSyncQueueTestHandler::class, ['foo' => 'bar']);
    } catch (Exception) {
        expect($_SERVER['__sync.failed'])->toBeTrue();
    }

    Vessel::setInstance();
});

test('failed job has access to job instance', function () {
    unset($_SERVER['__sync.failed']);

    $sync = new SyncQueue;
    $container = new Vessel;
    $container->bind(\Voyager\Contracts\Events\Dispatcher::class, \Voyager\Events\Dispatcher::class);
    $container->bind(\Voyager\Contracts\Bus\Dispatcher::class, \Voyager\Bus\Dispatcher::class);
    $container->bind(\Voyager\Contracts\Vessel\Vessel::class, \Voyager\Vessel\Vessel::class);
    $sync->setContainer($container);

    SyncQueue::createPayloadUsing(function ($connection, $queue, $payload) {
        return ['data' => ['extra' => 'extraValue']];
    });

    try {
        $sync->push(new FailingSyncQueueJob());
    } catch (LogicException) {
        expect($_SERVER['__sync.failed'])->toBe('extraValue');
    }
});

test('creates payload object', function () {
    $sync = new SyncQueue;
    $container = new Vessel;
    $container->bind(\Voyager\Contracts\Events\Dispatcher::class, \Voyager\Events\Dispatcher::class);
    $container->bind(\Voyager\Contracts\Bus\Dispatcher::class, \Voyager\Bus\Dispatcher::class);
    $container->bind(\Voyager\Contracts\Vessel\Vessel::class, \Voyager\Vessel\Vessel::class);
    $sync->setContainer($container);

    SyncQueue::createPayloadUsing(function ($connection, $queue, $payload) {
        return ['data' => ['extra' => 'extraValue']];
    });

    try {
        $sync->push(new SyncQueueJob());
    } catch (LogicException $e) {
        expect($e->getMessage())->toBe('extraValue');
    }
});

// Laravel covers the four after-commit dispatch paths here with mocks of
// Database's DatabaseTransactionsManager. They come back with Database in
// wave 6; SyncQueue's own after-commit logic is unchanged from upstream.

class SyncQueueTestEntity implements QueueableEntity
{
    public function getQueueableId()
    {
        return 1;
    }

    public function getQueueableConnection()
    {
        //
    }

    public function getQueueableRelations()
    {
        //
    }
}

class SyncQueueTestHandler
{
    public function fire($job, $data)
    {
        $_SERVER['__sync.test'] = func_get_args();
    }
}

class FailingSyncQueueTestHandler
{
    public function fire($job, $data)
    {
        throw new Exception;
    }

    public function failed()
    {
        $_SERVER['__sync.failed'] = true;
    }
}

class FailingSyncQueueJob implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle()
    {
        throw new LogicException();
    }

    public function failed()
    {
        $payload = $this->job->payload();

        $_SERVER['__sync.failed'] = $payload['data']['extra'];
    }
}

class SyncQueueJob implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle()
    {
        throw new LogicException($this->getValueFromJob('extra'));
    }

    public function getValueFromJob($key)
    {
        $payload = $this->job->payload();

        return $payload['data'][$key] ?? null;
    }
}

class SyncQueueAfterCommitJob
{
    use InteractsWithQueue;

    public $afterCommit = true;

    public function handle()
    {
    }
}

class SyncQueueAfterCommitInterfaceJob implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function handle()
    {
    }
}

class SyncQueueAfterCommitUniqueJob implements ShouldBeUnique
{
    use InteractsWithQueue;

    public $afterCommit = true;

    public function handle()
    {
    }
}

class SyncQueueAfterCommitInterfaceUniqueJob implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function handle()
    {
    }
}
