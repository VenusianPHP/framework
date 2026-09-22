<?php

use Voyager\Contracts\Queue\Queue as QueueContract;
use Voyager\Vessel\ControlPanel as Vessel;
use Voyager\Contracts\Signals\SignalDispatcher as Dispatcher;
use Voyager\Queue\FailoverQueue;
use Voyager\Queue\QueueManager;
use Mockery as m;

afterEach(function () {
    Vessel::setInstance(null);
});

test('push fails over on exception', function () {
    $failover = new FailoverQueue($queue = m::mock(QueueManager::class), $events = m::mock(Dispatcher::class), [
        'redis',
        'sync',
    ]);

    $queue->shouldReceive('connection')->once()->with('redis')->andReturn(
        $redis = m::mock(QueueContract::class),
    );

    $queue->shouldReceive('connection')->once()->with('sync')->andReturn(
        $sync = m::mock(QueueContract::class),
    );

    $events->shouldReceive('dispatch')->once();

    $redis->shouldReceive('push')->once()->andReturnUsing(
        fn () => throw new \Exception('error')
    );

    $sync->shouldReceive('push')->once();

    $failover->push('some-job');
});
