<?php

use Voyager\Vessel\Vessel;
use Voyager\Contracts\Events\Dispatcher;
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
        $redis = m::mock('stdClass'),
    );

    $queue->shouldReceive('connection')->once()->with('sync')->andReturn(
        $sync = m::mock('stdClass'),
    );

    $events->shouldReceive('dispatch')->once();

    $redis->shouldReceive('push')->once()->andReturnUsing(
        fn () => throw new \Exception('error')
    );

    $sync->shouldReceive('push')->once();

    $failover->push('some-job');
});
