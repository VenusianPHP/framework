<?php

namespace Tests\Queue;

use Voyager\Vessel\Vessel;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Queue\FailoverQueue;
use Voyager\Queue\QueueManager;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class FailoverQueueTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        Vessel::setInstance(null);

        parent::tearDown();
    }

    public function test_push_fails_over_on_exception()
    {
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
    }
}
