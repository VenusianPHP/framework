<?php

use Tests\Events\Fixtures\TestDispatcherConnectionQueuedHandler;
use Tests\Events\Fixtures\TestDispatcherGetConnection;
use Tests\Events\Fixtures\TestDispatcherGetConnectionDynamically;
use Tests\Events\Fixtures\TestDispatcherGetDelay;
use Tests\Events\Fixtures\TestDispatcherGetDelayDynamically;
use Tests\Events\Fixtures\TestDispatcherGetQueue;
use Tests\Events\Fixtures\TestDispatcherGetQueueDynamically;
use Tests\Events\Fixtures\TestDispatcherMiddleware;
use Tests\Events\Fixtures\TestDispatcherOptions;
use Tests\Events\Fixtures\TestDispatcherQueuedHandler;
use Tests\Events\Fixtures\TestDispatcherShouldBeUnique;
use Tests\Events\Fixtures\TestDispatcherShouldBeUniqueUntilProcessing;
use Tests\Events\Fixtures\TestDispatcherShouldBeUniqueWithCustomCache;
use Tests\Events\Fixtures\TestDispatcherUniqueIdFromMethod;
use Tests\Events\Fixtures\TestDispatcherViaQueueSupportsEnum;
use Tests\Events\Fixtures\TestDispatcherWithDeduplicationIdMethod;
use Tests\Events\Fixtures\TestDispatcherWithDeduplicatorMethod;
use Tests\Events\Fixtures\TestDispatcherWithMessageGroupMethod;
use Tests\Events\Fixtures\TestDispatcherWithMessageGroupProperty;
use Tests\Events\Fixtures\TestMiddleware;
use Tests\Events\Fixtures\TestQueueType;
use Laravel\SerializableClosure\SerializableClosure;
use Voyager\Bus\Dispatcher as BusDispatcher;
use Voyager\Contracts\Cache\Repository as Cache;
use Voyager\Contracts\Queue\Job;
use Voyager\Contracts\Queue\Queue;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Events\CallQueuedListener;
use Voyager\Events\Dispatcher;
use Voyager\Queue\CallQueuedHandler;
use Voyager\Queue\InteractsWithQueue;
use Voyager\Queue\QueueManager;
use Voyager\Testing\Fakes\QueueFake;
use Voyager\Vessel\Vessel;

test('queued event handlers are queued', function () {
        $d = new Dispatcher;
        $queue = Mockery::mock(Queue::class);

        $queue->shouldReceive('connection')->once()->with(null)->andReturnSelf();

        $queue->shouldReceive('pushOn')->once()->with(null, Mockery::type(CallQueuedListener::class));

        $d->setQueueResolver(function () use ($queue) {
            return $queue;
        });

        $d->listen('some.event', TestDispatcherQueuedHandler::class.'@someMethod');
        $d->dispatch('some.event', ['foo', 'bar']);
    });

test('customized queued event handlers are queued', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherConnectionQueuedHandler::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushedOn('my_queue', CallQueuedListener::class);
    });

test('queue is set by get queue', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherGetQueue::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushedOn('some_other_queue', CallQueuedListener::class);
    });

test('queue is set by get connection', function () {
        $d = new Dispatcher;
        $queue = Mockery::mock(Queue::class);

        $queue->shouldReceive('connection')->once()->with('some_other_connection')->andReturnSelf();

        $queue->shouldReceive('pushOn')->once()->with(null, Mockery::type(CallQueuedListener::class));

        $d->setQueueResolver(function () use ($queue) {
            return $queue;
        });

        $d->listen('some.event', TestDispatcherGetConnection::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);
    });

test('delay is set by with delay', function () {
        $d = new Dispatcher;
        $queue = Mockery::mock(Queue::class);

        $queue->shouldReceive('connection')->once()->with(null)->andReturnSelf();

        $queue->shouldReceive('laterOn')->once()->with(null, 20, Mockery::type(CallQueuedListener::class));

        $d->setQueueResolver(function () use ($queue) {
            return $queue;
        });

        $d->listen('some.event', TestDispatcherGetDelay::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);
    });

test('queue is set by get queue dynamically', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherGetQueueDynamically::class.'@handle');
        $d->dispatch('some.event', [['useHighPriorityQueue' => true], 'bar']);

        $fakeQueue->assertPushedOn('p0', CallQueuedListener::class);
    });

test('queue is set by get connection dynamically', function () {
        $d = new Dispatcher;
        $queueManager = $this->createMock(QueueManager::class);
        $queue = $this->createMock(Queue::class);

        $queueManager->expects($this->once())
            ->method('connection')
            ->with('redis')
            ->willReturn($queue);

        $queue->expects($this->once())
            ->method('pushOn')
            ->with(null, $this->isInstanceOf(CallQueuedListener::class));

        $d->setQueueResolver(function () use ($queueManager) {
            return $queueManager;
        });

        $d->listen('some.event', TestDispatcherGetConnectionDynamically::class.'@handle');
        $d->dispatch('some.event', [
            ['shouldUseRedisConnection' => true],
            'bar',
        ]);
    });

test('delay is set by with delay dynamically', function () {
        $d = new Dispatcher;
        $queue = Mockery::mock(Queue::class);

        $queue->shouldReceive('connection')->once()->with(null)->andReturnSelf();

        $queue->shouldReceive('laterOn')->once()->with(null, 60, Mockery::type(CallQueuedListener::class));

        $d->setQueueResolver(function () use ($queue) {
            return $queue;
        });

        $d->listen('some.event', TestDispatcherGetDelayDynamically::class.'@handle');
        $d->dispatch('some.event', [['useHighDelay' => true], 'bar']);
    });

test('queue propagate retry until and max exceptions', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherOptions::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            return $job->maxExceptions === 1 && $job->retryUntil !== null;
        });
    });

test('queue propagate tries', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherOptions::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            return $job->tries === 5;
        });
    });

test('queue propagate message group property', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherWithMessageGroupProperty::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            return $job->messageGroup === 'group-property';
        });
    });

test('queue propagate message group method over property', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherWithMessageGroupMethod::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            return $job->messageGroup === 'group-method';
        });
    });

test('queue propagate deduplication id method', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherWithDeduplicationIdMethod::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            expect($job->deduplicator)->toBeInstanceOf(SerializableClosure::class);

            return is_callable($job->deduplicator) && call_user_func($job->deduplicator, '', null) === 'deduplication-id-method';
        });
    });

test('queue propagate deduplicator method over deduplication id method', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherWithDeduplicatorMethod::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            expect($job->deduplicator)->toBeInstanceOf(SerializableClosure::class);

            return is_callable($job->deduplicator) && call_user_func($job->deduplicator, '', null) === 'deduplicator-method';
        });
    });

test('queue propagate middleware', function () {
        $d = new Dispatcher;

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherMiddleware::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            return count($job->middleware) === 1
                && $job->middleware[0] instanceof TestMiddleware
                && $job->middleware[0]->a === 'foo'
                && $job->middleware[0]->b === 'bar';
        });
    });

test('dispatches on queue defined with enum', function () {
        $d = new Dispatcher;
        $queue = Mockery::mock(Queue::class);

        $fakeQueue = new QueueFake(new Vessel);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherViaQueueSupportsEnum::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushedOn('enumerated-queue', CallQueuedListener::class);
    });

test('queue propagates should be unique', function () {
        $vessel = new Vessel;
        $d = new Dispatcher($vessel);

        $fakeQueue = new QueueFake($vessel);
        $cache = Mockery::mock(Cache::class);
        $lock = Mockery::mock(Lock::class);

        $vessel->instance(Cache::class, $cache);

        $cache->shouldReceive('lock')->once()->andReturn($lock);
        $lock->shouldReceive('get')->once()->andReturn(true);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherShouldBeUnique::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            return $job->shouldBeUnique === true
                && $job->shouldBeUniqueUntilProcessing === false
                && $job->uniqueId === 'unique-listener-id'
                && $job->uniqueFor === 60;
        });
    });

test('unique listener not queued when lock not acquired', function () {
        $vessel = new Vessel;
        $d = new Dispatcher($vessel);

        $fakeQueue = new QueueFake($vessel);
        $cache = Mockery::mock(Cache::class);
        $lock = Mockery::mock(Lock::class);

        $vessel->instance(Cache::class, $cache);

        $cache->shouldReceive('lock')->once()->andReturn($lock);
        $lock->shouldReceive('get')->once()->andReturn(false);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherShouldBeUnique::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertNothingPushed();
    });

test('queue propagates should be unique until processing', function () {
        $vessel = new Vessel;
        $d = new Dispatcher($vessel);

        $fakeQueue = new QueueFake($vessel);
        $cache = Mockery::mock(Cache::class);
        $lock = Mockery::mock(Lock::class);

        $vessel->instance(Cache::class, $cache);

        $cache->shouldReceive('lock')->once()->andReturn($lock);
        $lock->shouldReceive('get')->once()->andReturn(true);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherShouldBeUniqueUntilProcessing::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            return $job->shouldBeUnique === true
                && $job->shouldBeUniqueUntilProcessing === true;
        });
    });

test('queue propagates unique id from method', function () {
        $vessel = new Vessel;
        $d = new Dispatcher($vessel);

        $fakeQueue = new QueueFake($vessel);
        $cache = Mockery::mock(Cache::class);
        $lock = Mockery::mock(Lock::class);

        $vessel->instance(Cache::class, $cache);

        $cache->shouldReceive('lock')->once()->andReturn($lock);
        $lock->shouldReceive('get')->once()->andReturn(true);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherUniqueIdFromMethod::class.'@handle');
        $d->dispatch('some.event', [['id' => 'event-123'], 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class, function ($job) {
            return $job->uniqueId === 'unique-id-event-123';
        });
    });

test('unique lock key uses listener class name', function () {
        $listener = new CallQueuedListener(TestDispatcherShouldBeUnique::class, 'handle', []);
        $listener->shouldBeUnique = true;
        $listener->uniqueId = 'test-id';

        expect($listener->displayName())->toBe(TestDispatcherShouldBeUnique::class);
        expect(\Voyager\Bus\UniqueLock::getKey($listener))->toBe('laravel_unique_job:'.hash('xxh128', TestDispatcherShouldBeUnique::class).':test-id');
    });

test('unique lock is acquired with listener class name', function () {
        $vessel = new Vessel;
        $d = new Dispatcher($vessel);

        $fakeQueue = new QueueFake($vessel);
        $cache = Mockery::mock(Cache::class);
        $lock = Mockery::mock(Lock::class);

        $vessel->instance(Cache::class, $cache);

        $expectedKey = 'laravel_unique_job:'.hash('xxh128', TestDispatcherShouldBeUnique::class).':unique-listener-id';

        $cache->shouldReceive('lock')
            ->once()
            ->with($expectedKey, 60)
            ->andReturn($lock);
        $lock->shouldReceive('get')->once()->andReturn(true);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherShouldBeUnique::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class);
    });

test('unique via uses listener cache repository', function () {
        $vessel = new Vessel;
        $d = new Dispatcher($vessel);

        $fakeQueue = new QueueFake($vessel);
        $defaultCache = Mockery::mock(Cache::class);
        $uniqueCache = Mockery::mock(Cache::class);
        $lock = Mockery::mock(Lock::class);

        $vessel->instance(Cache::class, $defaultCache);

        $defaultCache->shouldNotReceive('lock');

        TestDispatcherShouldBeUniqueWithCustomCache::$cache = $uniqueCache;

        $expectedKey = 'laravel_unique_job:'.hash('xxh128', TestDispatcherShouldBeUniqueWithCustomCache::class).':unique-listener-id';

        $uniqueCache->shouldReceive('lock')
            ->once()
            ->with($expectedKey, 60)
            ->andReturn($lock);
        $lock->shouldReceive('get')->once()->andReturn(true);

        $d->setQueueResolver(function () use ($fakeQueue) {
            return $fakeQueue;
        });

        $d->listen('some.event', TestDispatcherShouldBeUniqueWithCustomCache::class.'@handle');
        $d->dispatch('some.event', ['foo', 'bar']);

        $fakeQueue->assertPushed(CallQueuedListener::class);
    });

test('unique lock is released on processing with listener class name', function () {
        $vessel = new Vessel;
        $cache = Mockery::mock(Cache::class);
        $lock = Mockery::mock(Lock::class);

        $vessel->instance(Cache::class, $cache);
        $vessel->instance(BusDispatcher::class, new BusDispatcher($vessel));

        $listener = new CallQueuedListener(TestDispatcherShouldBeUnique::class, 'handle', ['foo', 'bar']);
        $listener->shouldBeUnique = true;
        $listener->uniqueId = 'unique-listener-id';
        $listener->uniqueFor = 60;

        $expectedKey = 'laravel_unique_job:'.hash('xxh128', TestDispatcherShouldBeUnique::class).':unique-listener-id';

        $cache->shouldReceive('lock')
            ->once()
            ->with($expectedKey)
            ->andReturn($lock);
        $lock->shouldReceive('forceRelease')->once();

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('hasFailed')->andReturn(false);
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('isDeletedOrReleased')->andReturn(false);
        $job->shouldReceive('delete')->once();

        $handler = new CallQueuedHandler(new BusDispatcher($vessel), $vessel);
        $handler->call($job, ['command' => serialize($listener)]);
    });

test('unique until processing lock is released before handling', function () {
        $vessel = new Vessel;
        $cache = Mockery::mock(Cache::class);
        $lock = Mockery::mock(Lock::class);

        $vessel->instance(Cache::class, $cache);
        $vessel->instance(BusDispatcher::class, new BusDispatcher($vessel));

        TestDispatcherShouldBeUniqueUntilProcessing::$lockReleasedBeforeHandling = null;
        TestDispatcherShouldBeUniqueUntilProcessing::$cache = $cache;
        TestDispatcherShouldBeUniqueUntilProcessing::$expectedLockKey = 'laravel_unique_job:'.hash('xxh128', TestDispatcherShouldBeUniqueUntilProcessing::class).':until-processing-id';

        $listener = new CallQueuedListener(TestDispatcherShouldBeUniqueUntilProcessing::class, 'handle', ['foo', 'bar']);
        $listener->shouldBeUnique = true;
        $listener->shouldBeUniqueUntilProcessing = true;
        $listener->uniqueId = 'until-processing-id';

        $expectedKey = 'laravel_unique_job:'.hash('xxh128', TestDispatcherShouldBeUniqueUntilProcessing::class).':until-processing-id';

        $cache->shouldReceive('lock')
            ->with($expectedKey)
            ->andReturn($lock);
        $lock->shouldReceive('forceRelease')->once();

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('hasFailed')->andReturn(false);
        $job->shouldReceive('isDeleted')->andReturn(false);
        $job->shouldReceive('isReleased')->andReturn(false);
        $job->shouldReceive('isDeletedOrReleased')->andReturn(false);
        $job->shouldReceive('delete')->once();

        $handler = new CallQueuedHandler(new BusDispatcher($vessel), $vessel);
        $handler->call($job, ['command' => serialize($listener)]);

        expect(TestDispatcherShouldBeUniqueUntilProcessing::$lockReleasedBeforeHandling)->toBeTrue();
    });

