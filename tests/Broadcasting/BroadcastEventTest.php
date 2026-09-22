<?php

use Voyager\Broadcasting\BroadcastEvent;
use Voyager\Broadcasting\InteractsWithBroadcasting;
use Voyager\Contracts\Broadcasting\Broadcaster;
use Voyager\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Voyager\Vessel\ControlPanel;
use Mockery as m;

beforeEach(fn () => ControlPanel::setInstance(new ControlPanel));
afterEach(function () {
    ControlPanel::setInstance(null);
    m::close();
});

function bindBroadcastManager(BroadcastingFactory $manager): void
{
    app()->registerInstance(BroadcastingFactory::class, $manager);
}

test('basic event broadcast parameter formatting', function () {
    $broadcaster = m::mock(Broadcaster::class);

    $broadcaster->shouldReceive('broadcast')->once()->with(
        ['test-channel'], TestBroadcastEvent::class, ['firstName' => 'Taylor', 'lastName' => 'Otwell', 'collection' => ['foo' => 'bar']]
    );

    $manager = m::mock(BroadcastingFactory::class);

    $manager->shouldReceive('connection')->once()->with(null)->andReturn($broadcaster);

    $event = new TestBroadcastEvent;

    bindBroadcastManager($manager);
    (new BroadcastEvent($event))->handle();
});

test('manual parameter specification', function () {
    $broadcaster = m::mock(Broadcaster::class);

    $broadcaster->shouldReceive('broadcast')->once()->with(
        ['test-channel'], TestBroadcastEventWithManualData::class, ['name' => 'Taylor', 'socket' => null]
    );

    $manager = m::mock(BroadcastingFactory::class);

    $manager->shouldReceive('connection')->once()->with(null)->andReturn($broadcaster);

    $event = new TestBroadcastEventWithManualData;

    bindBroadcastManager($manager);
    (new BroadcastEvent($event))->handle();
});

test('specific broadcaster given', function () {
    $broadcaster = m::mock(Broadcaster::class);

    $broadcaster->shouldReceive('broadcast')->once();

    $manager = m::mock(BroadcastingFactory::class);

    $manager->shouldReceive('connection')->once()->with('log')->andReturn($broadcaster);

    $event = new TestBroadcastEventWithSpecificBroadcaster;

    bindBroadcastManager($manager);
    (new BroadcastEvent($event))->handle();
});

test('specific channels per connection', function () {
    $broadcaster = m::mock(Broadcaster::class);

    $broadcaster->shouldReceive('broadcast')->once()->with(
        ['first-channel'], TestBroadcastEventWithChannelsPerConnection::class, ['firstName' => 'Taylor', 'lastName' => 'Otwell', 'collection' => ['foo' => 'bar']]
    );

    $broadcaster->shouldReceive('broadcast')->once()->with(
        ['second-channel'], TestBroadcastEventWithChannelsPerConnection::class, ['firstName' => 'Taylor']
    );

    $manager = m::mock(BroadcastingFactory::class);

    $manager->shouldReceive('connection')->once()->with('first_connection')->andReturn($broadcaster);
    $manager->shouldReceive('connection')->once()->with('second_connection')->andReturn($broadcaster);

    $event = new TestBroadcastEventWithChannelsPerConnection;

    bindBroadcastManager($manager);
    (new BroadcastEvent($event))->handle();
});

test('middleware proxies middleware from underlying event', function () {
    $event = new class
    {
        public function middleware(): array
        {
            return ['foo', 'bar'];
        }
    };

    $job = new BroadcastEvent($event);

    expect($job->middleware())->toBe(['foo', 'bar']);
});

test('middleware proxies failed handler from underlying event', function () {
    $event = new class
    {
        public function failed(?Throwable $e = null): void
        {
            $e->validateCall();
        }
    };

    $job = new BroadcastEvent($event);

    $exception = m::mock(Exception::class);
    $exception->expects('validateCall');

    $job->failed($exception);
});

class TestBroadcastEvent
{
    public $firstName = 'Taylor';
    public $lastName = 'Otwell';
    public $collection;
    private $title = 'Developer';

    public function __construct()
    {
        $this->collection = collect(['foo' => 'bar']);
    }

    public function broadcastOn()
    {
        return ['test-channel'];
    }
}

class TestBroadcastEventWithManualData extends TestBroadcastEvent
{
    public function broadcastWith()
    {
        return ['name' => 'Taylor'];
    }
}

class TestBroadcastEventWithSpecificBroadcaster extends TestBroadcastEvent
{
    use InteractsWithBroadcasting;

    public function __construct()
    {
        $this->broadcastVia('log');
    }
}

class TestBroadcastEventWithChannelsPerConnection extends TestBroadcastEvent
{
    public function broadcastConnections()
    {
        return [
            'first_connection',
            'second_connection',
        ];
    }

    public function broadcastWith()
    {
        return [
            'first_connection' => [
                'firstName' => 'Taylor',
                'lastName' => 'Otwell',
                'collection' => ['foo' => 'bar'],
            ],
            'second_connection' => [
                'firstName' => 'Taylor',
            ],
        ];
    }

    public function broadcastOn()
    {
        return [
            'first_connection' => ['first-channel'],
            'second_connection' => ['second-channel'],
        ];
    }
}
