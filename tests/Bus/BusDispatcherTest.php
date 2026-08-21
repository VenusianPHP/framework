<?php

namespace Tests\Bus;

use Voyager\Bus\Dispatcher;
use Voyager\Bus\Queueable;
use Voyager\Config\Repository as Config;
use Voyager\Vessel\Vessel;
use Voyager\Contracts\Queue\Queue;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Queue\InteractsWithQueue;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BusDispatcherTest extends TestCase
{
    public function testCommandsThatShouldQueueIsQueued()
    {
        $vessel = new Vessel;
        $dispatcher = new Dispatcher($vessel, function () {
            $mock = m::mock(Queue::class);
            $mock->shouldReceive('push')->once();

            return $mock;
        });

        $dispatcher->dispatch(m::mock(ShouldQueue::class));
    }

    public function testCommandsThatShouldQueueIsQueuedUsingCustomHandler()
    {
        $vessel = new Vessel;
        $dispatcher = new Dispatcher($vessel, function () {
            $mock = m::mock(Queue::class);
            $mock->shouldReceive('push')->once();

            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherTestCustomQueueCommand);
    }

    public function testCommandsThatShouldQueueIsQueuedUsingCustomQueueAndDelay()
    {
        $vessel = new Vessel;
        $dispatcher = new Dispatcher($vessel, function () {
            $mock = m::mock(Queue::class);
            $mock->shouldReceive('later')->once()->with(10, m::type(BusDispatcherTestSpecificQueueAndDelayCommand::class), '', 'foo');

            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherTestSpecificQueueAndDelayCommand);
    }

    public function testDispatchNowShouldNeverQueue()
    {
        $vessel = new Vessel;
        $mock = m::mock(Queue::class);
        $mock->shouldReceive('push')->never();
        $dispatcher = new Dispatcher($vessel, function () use ($mock) {
            return $mock;
        });

        $dispatcher->dispatch(new BusDispatcherBasicCommand);
    }

    public function testDispatcherCanDispatchStandAloneHandler()
    {
        $vessel = new Vessel;
        $mock = m::mock(Queue::class);
        $dispatcher = new Dispatcher($vessel, function () use ($mock) {
            return $mock;
        });

        $dispatcher->map([StandAloneCommand::class => StandAloneHandler::class]);

        $response = $dispatcher->dispatch(new StandAloneCommand);

        $this->assertInstanceOf(StandAloneCommand::class, $response);
    }

    public function testOnConnectionOnJobWhenDispatching()
    {
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
            $mock = m::mock(Queue::class);
            $mock->shouldReceive('push')->once();

            return $mock;
        });

        $job = (new ShouldNotBeDispatched)->onConnection('null');

        $dispatcher->dispatch($job);
    }
}

class BusInjectionStub
{
    //
}

class BusDispatcherBasicCommand
{
    public $name;

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function handle(BusInjectionStub $stub)
    {
        //
    }
}

class BusDispatcherTestCustomQueueCommand implements ShouldQueue
{
    public function queue($queue, $command)
    {
        $queue->push($command);
    }
}

class BusDispatcherTestSpecificQueueAndDelayCommand implements ShouldQueue
{
    public $queue = 'foo';
    public $delay = 10;
}

class StandAloneCommand
{
    //
}

class StandAloneHandler
{
    public function handle(StandAloneCommand $command)
    {
        return $command;
    }
}

class ShouldNotBeDispatched implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function handle()
    {
        throw new RuntimeException('This should not be run');
    }
}
