<?php

use Voyager\Broadcasting\BroadcastEvent;
use Voyager\Broadcasting\BroadcastManager;
use Voyager\Broadcasting\InteractsWithSockets;
use Voyager\Broadcasting\PendingBroadcast;
use Voyager\Contracts\Broadcasting\Factory as BroadcastFactory;
use Voyager\Contracts\Broadcasting\ShouldBroadcast;
use Voyager\Contracts\Queue\Factory as QueueFactory;
use Voyager\Contracts\Queue\Queue;
use Voyager\Contracts\Signals\SignalDispatcher as SignalDispatcherContract;
use Voyager\Signals\SignalDispatcher;
use Voyager\Vessel\ControlPanel;
use Mockery as m;

afterEach(function () {
    ControlPanel::setInstance(null);
    m::close();
});

test('dontBroadcastToCurrentUser with no socket is ArgumentCountError', function () {
    $event = new class {
        use InteractsWithSockets;
    };

    expect(fn () => $event->dontBroadcastToCurrentUser())->toThrow(ArgumentCountError::class);
});

test('dontBroadcastToCurrentUser sets the socket', function () {
    $event = new class {
        use InteractsWithSockets;
    };

    $event->dontBroadcastToCurrentUser('socket-abc');

    expect($event->socket)->toBe('socket-abc');
});

test('PendingBroadcast toOthers with no socket is ArgumentCountError', function () {
    $signals = m::mock(SignalDispatcherContract::class);
    $signals->shouldReceive('dispatch');
    $event = new class {
        use InteractsWithSockets;
    };
    $pending = new PendingBroadcast($signals, $event);

    expect(fn () => $pending->toOthers())->toThrow(ArgumentCountError::class);
});

test('PendingBroadcast toOthers sets the socket', function () {
    $signals = m::mock(SignalDispatcherContract::class);
    $signals->shouldReceive('dispatch');
    $event = new class {
        use InteractsWithSockets;
    };
    $pending = new PendingBroadcast($signals, $event);

    $pending->toOthers('socket-abc');

    expect($event->socket)->toBe('socket-abc');
});

test('ShouldBroadcast signal is pushOn as BroadcastEvent on the named queue', function () {
    $app = new ControlPanel;
    ControlPanel::setInstance($app);

    $queues = m::mock(QueueFactory::class);
    $queue = m::mock(Queue::class);
    $queues->shouldReceive('connection')->once()->with('deferred')->andReturn($queue);
    $queue->shouldReceive('pushOn')->once()->with(
        'broadcasts',
        m::on(fn ($job) => $job instanceof BroadcastEvent
            && $job->event->connection === 'deferred'
            && $job->event->broadcastQueue === 'broadcasts')
    );

    $app->registerInstance('queue', $queues);
    $app->registerInstance(BroadcastFactory::class, new BroadcastManager($app));

    $event = new class implements ShouldBroadcast {
        public string $connection = 'deferred';
        public string $broadcastQueue = 'broadcasts';

        public function broadcastOn(): array
        {
            return ['orders'];
        }
    };

    (new SignalDispatcher($app))->dispatch($event);
});
