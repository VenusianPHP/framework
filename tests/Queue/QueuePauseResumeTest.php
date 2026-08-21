<?php

use Voyager\Cache\ArrayStore;
use Voyager\Cache\Repository;
use Voyager\Events\Dispatcher;
use Voyager\Queue\Console\Concerns\ParsesQueue;
use Voyager\Queue\Events\QueuePaused;
use Voyager\Queue\Events\QueueResumed;
use Voyager\Queue\QueueManager;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mockery as m;

beforeEach(function () {
    $this->cache = new Repository(new ArrayStore);

    // Mock the cache facade to return our cache repository
    $cacheMock = m::mock();
    $cacheMock->shouldReceive('store')->andReturn($this->cache);

    $app = [
        'config' => [
            'queue.default' => 'redis',
            'queue.connections.redis' => ['driver' => 'redis'],
            'queue.connections.database' => ['driver' => 'database'],
        ],
        'cache' => $cacheMock,
        'events' => new Dispatcher(),
    ];

    $this->manager = new QueueManager($app);
});

test('pause queue with connection', function () {
    $this->manager->pause('redis', 'default');

    expect($this->manager->isPaused('redis', 'default'))->toBeTrue();
});

test('pause queue with t t l', function () {
    Carbon::setTestNow();
    $this->manager->pauseFor('redis', 'default', 30);

    expect($this->manager->isPaused('redis', 'default'))->toBeTrue();

    Carbon::setTestNow(Carbon::now()->addMinute());
    expect($this->manager->isPaused('redis', 'default'))->toBeFalse();
});

test('pause queue indefinitely', function () {
    Carbon::setTestNow();
    $this->manager->pause('redis', 'default');

    expect($this->manager->isPaused('redis', 'default'))->toBeTrue();

    Carbon::setTestNow(Carbon::now()->addYear());
    expect($this->manager->isPaused('redis', 'default'))->toBeTrue();
});

test('resume queue', function () {
    $this->manager->pause('redis', 'default');
    expect($this->manager->isPaused('redis', 'default'))->toBeTrue();

    $this->manager->resume('redis', 'default');
    expect($this->manager->isPaused('redis', 'default'))->toBeFalse();
});

test('pausing queue on one connection does not affect another', function () {
    $this->manager->pause('redis', 'default');

    expect($this->manager->isPaused('redis', 'default'))->toBeTrue();
    expect($this->manager->isPaused('database', 'default'))->toBeFalse();
});

test('pausing different queues on same connection', function () {
    $this->manager->pause('redis', 'emails');
    $this->manager->pause('redis', 'notifications');

    expect($this->manager->isPaused('redis', 'emails'))->toBeTrue();
    expect($this->manager->isPaused('redis', 'notifications'))->toBeTrue();
    expect($this->manager->isPaused('redis', 'default'))->toBeFalse();
});

test('resuming only affects specific queue', function () {
    $this->manager->pause('redis', 'emails');
    $this->manager->pause('redis', 'notifications');

    $this->manager->resume('redis', 'emails');

    expect($this->manager->isPaused('redis', 'emails'))->toBeFalse();
    expect($this->manager->isPaused('redis', 'notifications'))->toBeTrue();
});

test('pause dispatches queue paused event', function () {
    $dispatchedEvent = null;

    $dispatcher = $this->manager->getApplication()['events'];

    $dispatcher->listen(QueuePaused::class, function ($event) use (&$dispatchedEvent) {
        $dispatchedEvent = $event;
    });

    $this->manager->pause('redis', 'default');

    expect($dispatchedEvent)->toBeInstanceOf(QueuePaused::class)
        ->and($dispatchedEvent->connection)->toBe('redis')
        ->and($dispatchedEvent->queue)->toBe('default')
        ->and($dispatchedEvent->ttl)->toBeNull();
});

test('pause for dispatches queue paused event with t t l', function () {
    $dispatchedEvent = null;

    $dispatcher = $this->manager->getApplication()['events'];

    $dispatcher->listen(QueuePaused::class, function ($event) use (&$dispatchedEvent) {
        $dispatchedEvent = $event;
    });

    $this->manager->pauseFor('redis', 'emails', 60);

    expect($dispatchedEvent)->toBeInstanceOf(QueuePaused::class)
        ->and($dispatchedEvent->connection)->toBe('redis')
        ->and($dispatchedEvent->queue)->toBe('emails')
        ->and($dispatchedEvent->ttl)->toBe(60);
});

test('resume dispatches queue resumed event', function () {
    $dispatchedEvent = null;

    $dispatcher = $this->manager->getApplication()['events'];

    $dispatcher->listen(QueueResumed::class, function ($event) use (&$dispatchedEvent) {
        $dispatchedEvent = $event;
    });

    $this->manager->resume('database', 'notifications');

    expect($dispatchedEvent)->toBeInstanceOf(QueueResumed::class)
        ->and($dispatchedEvent->connection)->toBe('database')
        ->and($dispatchedEvent->queue)->toBe('notifications');
});

test('parsing queue string', function () {
    $parser = new class()
    {
        use ParsesQueue;

        private array $venusian = [
            'config' => ['queue.default' => 'redis'],
        ];

        public function parse(string $queue)
        {
            return $this->parseQueue($queue);
        }
    };

    expect($parser->parse(''))->toBe(['redis', 'default']);
    expect($parser->parse('emails'))->toBe(['redis', 'emails']);
    expect($parser->parse('database:notifications'))->toBe(['database', 'notifications']);
    expect($parser->parse('redis:foo:bar'))->toBe(['redis', 'foo:bar']);
});
