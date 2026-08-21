<?php

use Tests\Bus\Fixtures\BusDispatcherBasicCommand;
use Tests\Bus\Fixtures\BusDispatcherTestCustomQueueCommand;
use Tests\Bus\Fixtures\BusDispatcherTestSpecificQueueAndDelayCommand;
use Tests\Bus\Fixtures\ShouldNotBeDispatched;
use Tests\Bus\Fixtures\StandAloneCommand;
use Tests\Bus\Fixtures\StandAloneHandler;
use Voyager\Bus\Dispatcher;
use Voyager\Config\Repository as Config;
use Voyager\Contracts\Queue\Queue;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Vessel\Vessel;

test('commands that should queue is queued', function () {
    $vessel = new Vessel;
    $dispatcher = new Dispatcher($vessel, function () {
        $mock = Mockery::mock(Queue::class);
        $mock->shouldReceive('push')->once();

        return $mock;
    });

    $dispatcher->dispatch(Mockery::mock(ShouldQueue::class));
});

test('commands that should queue is queued using custom handler', function () {
    $vessel = new Vessel;
    $dispatcher = new Dispatcher($vessel, function () {
        $mock = Mockery::mock(Queue::class);
        $mock->shouldReceive('push')->once();

        return $mock;
    });

    $dispatcher->dispatch(new BusDispatcherTestCustomQueueCommand);
});

test('commands that should queue is queued using custom queue and delay', function () {
    $vessel = new Vessel;
    $dispatcher = new Dispatcher($vessel, function () {
        $mock = Mockery::mock(Queue::class);
        $mock->shouldReceive('later')->once()->with(10, Mockery::type(BusDispatcherTestSpecificQueueAndDelayCommand::class), '', 'foo');

        return $mock;
    });

    $dispatcher->dispatch(new BusDispatcherTestSpecificQueueAndDelayCommand);
});

test('dispatch now should never queue', function () {
    $vessel = new Vessel;
    $mock = Mockery::mock(Queue::class);
    $mock->shouldReceive('push')->never();
    $dispatcher = new Dispatcher($vessel, function () use ($mock) {
        return $mock;
    });

    $dispatcher->dispatch(new BusDispatcherBasicCommand);
});

test('dispatcher can dispatch stand alone handler', function () {
    $vessel = new Vessel;
    $mock = Mockery::mock(Queue::class);
    $dispatcher = new Dispatcher($vessel, function () use ($mock) {
        return $mock;
    });

    $dispatcher->map([StandAloneCommand::class => StandAloneHandler::class]);

    $response = $dispatcher->dispatch(new StandAloneCommand);

    expect($response)->toBeInstanceOf(StandAloneCommand::class);
});

test('on connection on job when dispatching', function () {
    $vessel = new Vessel;
    $vessel->singleton('config', function () {
        return new Config([
            'queue' => [
                'default' => 'null',
                'connections' => [
                    'null' => ['driver' => 'null'],
                ],
            ],
        ]);
    });

    $dispatcher = new Dispatcher($vessel, function () {
        $mock = Mockery::mock(Queue::class);
        $mock->shouldReceive('push')->once();

        return $mock;
    });

    $job = (new ShouldNotBeDispatched)->onConnection('null');

    $dispatcher->dispatch($job);
});
