<?php

use Tests\Events\Fixtures\AlwaysBroadcastEvent;
use Tests\Events\Fixtures\BroadcastableNamedArgumentsEvent;
use Tests\Events\Fixtures\BroadcastEvent;
use Tests\Events\Fixtures\BroadcastFalseCondition;
use Tests\Events\Fixtures\ExampleEvent;
use Voyager\Broadcasting\PendingBroadcast;
use Voyager\Contracts\Broadcasting\Factory as BroadcastFactory;
use Voyager\Contracts\Broadcasting\ShouldBroadcast;
use Voyager\Events\Dispatcher;
use Voyager\Vessel\Vessel;

test('should broadcast success', function () {
        $d = Mockery::mock(Dispatcher::class);

        $d->makePartial()->shouldAllowMockingProtectedMethods();

        $event = new BroadcastEvent;

        expect($d->shouldBroadcast([$event]))->toBeTrue();

        $event = new AlwaysBroadcastEvent;

        expect($d->shouldBroadcast([$event]))->toBeTrue();
    });

test('should broadcast as queued and call normal listeners', function () {
        unset($_SERVER['__event.test']);
        $d = new Dispatcher($vessel = Mockery::mock(Vessel::class));
        $broadcast = Mockery::mock(BroadcastFactory::class);
        $broadcast->shouldReceive('queue')->once();
        $vessel->shouldReceive('make')->once()->with(BroadcastFactory::class)->andReturn($broadcast);

        $d->listen(AlwaysBroadcastEvent::class, function ($payload) {
            $_SERVER['__event.test'] = $payload;
        });

        $d->dispatch($e = new AlwaysBroadcastEvent);

        expect($_SERVER['__event.test'])->toBe($e);
    });

test('should broadcast fail', function () {
        $d = Mockery::mock(Dispatcher::class);

        $d->makePartial()->shouldAllowMockingProtectedMethods();

        $event = new BroadcastFalseCondition;

        expect($d->shouldBroadcast([$event]))->toBeFalse();

        $event = new ExampleEvent;

        expect($d->shouldBroadcast([$event]))->toBeFalse();
    });

test('broadcast with multiple channels', function () {
        $d = new Dispatcher($vessel = Mockery::mock(Vessel::class));
        $broadcast = Mockery::mock(BroadcastFactory::class);
        $broadcast->shouldReceive('queue')->once();
        $vessel->shouldReceive('make')->once()->with(BroadcastFactory::class)->andReturn($broadcast);

        $event = new class implements ShouldBroadcast
        {
            public function broadcastOn()
            {
                return ['channel-1', 'channel-2'];
            }
        };

        $d->dispatch($event);
    });

test('broadcast with custom connection name', function () {
        $d = new Dispatcher($vessel = Mockery::mock(Vessel::class));
        $broadcast = Mockery::mock(BroadcastFactory::class);
        $broadcast->shouldReceive('queue')->once();
        $vessel->shouldReceive('make')->once()->with(BroadcastFactory::class)->andReturn($broadcast);

        $event = new class implements ShouldBroadcast
        {
            public $connection = 'custom-connection';

            public function broadcastOn()
            {
                return ['test-channel'];
            }
        };

        $d->dispatch($event);
    });

test('broadcast with custom event name', function () {
        $d = new Dispatcher($vessel = Mockery::mock(Vessel::class));
        $broadcast = Mockery::mock(BroadcastFactory::class);
        $broadcast->shouldReceive('queue')->once();
        $vessel->shouldReceive('make')->once()->with(BroadcastFactory::class)->andReturn($broadcast);

        $event = new class implements ShouldBroadcast
        {
            public function broadcastOn()
            {
                return ['test-channel'];
            }

            public function broadcastAs()
            {
                return 'custom-event-name';
            }
        };

        $d->dispatch($event);
    });

test('broadcast with custom payload', function () {
        $d = new Dispatcher($vessel = Mockery::mock(Vessel::class));
        $broadcast = Mockery::mock(BroadcastFactory::class);
        $broadcast->shouldReceive('queue')->once();
        $vessel->shouldReceive('make')->once()->with(BroadcastFactory::class)->andReturn($broadcast);

        $event = new class implements ShouldBroadcast
        {
            public $customData = 'test-data';

            public function broadcastOn()
            {
                return ['test-channel'];
            }

            public function broadcastWith()
            {
                return ['custom' => $this->customData];
            }
        };

        $d->dispatch($event);
    });

test('event broadcasts using named arguments', function () {
        $vessel = new Vessel;
        $broadcast = Mockery::mock(BroadcastFactory::class);
        $vessel->instance(BroadcastFactory::class, $broadcast);

        $originalContainer = Vessel::getInstance();
        Vessel::setInstance($vessel);

        try {
            $pendingBroadcast = Mockery::mock(PendingBroadcast::class);

            $broadcast->shouldReceive('event')
                ->once()
                ->with(Mockery::on(function ($event) {
                    expect($event)->toBeInstanceOf(BroadcastableNamedArgumentsEvent::class);
                    expect($event->first)->toBe('first-value');
                    expect($event->second)->toBe('second-value');

                    return true;
                }))
                ->andReturn($pendingBroadcast);

            expect(BroadcastableNamedArgumentsEvent::broadcast(second: 'second-value', first: 'first-value'))->toBe($pendingBroadcast);
        } finally {
            Vessel::setInstance($originalContainer);
        }
    });

