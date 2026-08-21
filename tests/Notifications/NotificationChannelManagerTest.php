<?php

use Voyager\Bus\Queueable;
use Voyager\Vessel\Vessel;
use Voyager\Contracts\Bus\Dispatcher as Bus;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Notifications\ChannelManager;
use Voyager\Notifications\Events\NotificationFailed;
use Voyager\Notifications\Events\NotificationSending;
use Voyager\Notifications\Events\NotificationSent;
use Voyager\Notifications\Notifiable;
use Voyager\Notifications\Notification;
use Voyager\Notifications\SendQueuedNotifications;
use Voyager\Queue\InteractsWithQueue;
use Voyager\Queue\Concerns\SerializesModels;
use Voyager\NutsAndBolts\Collection;
use Laravel\SerializableClosure\SerializableClosure;
use Mockery as m;

afterEach(function () {
    Vessel::setInstance(null);
});

test('notification can be dispatched to driver', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Bus::class, $bus = m::mock());
    $container->instance(Dispatcher::class, $events = m::mock());
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $manager->shouldReceive('driver')->andReturn($driver = m::mock());
    $events->shouldReceive('listen')->once();
    $events->shouldReceive('until')->with(m::type(NotificationSending::class))->andReturn(true);
    $driver->shouldReceive('send')->once();
    $events->shouldReceive('dispatch')->with(m::type(NotificationSent::class));

    $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
});

test('notification not sent on halt', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Bus::class, $bus = m::mock());
    $container->instance(Dispatcher::class, $events = m::mock());
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();
    $events->shouldReceive('until')->once()->with(m::type(NotificationSending::class))->andReturn(false);
    $events->shouldReceive('until')->with(m::type(NotificationSending::class))->andReturn(true);
    $manager->shouldReceive('driver')->once()->andReturn($driver = m::mock());
    $driver->shouldReceive('send')->once();
    $events->shouldReceive('dispatch')->with(m::type(NotificationSent::class));

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestNotificationWithTwoChannels);
});

test('notification not sent when cancelled', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Bus::class, $bus = m::mock());
    $container->instance(Dispatcher::class, $events = m::mock());
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();
    $events->shouldReceive('until')->with(m::type(NotificationSending::class))->andReturn(true);
    $manager->shouldNotReceive('driver');
    $events->shouldNotReceive('dispatch');

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestCancelledNotification);
});

test('notification sent when not cancelled', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Bus::class, $bus = m::mock());
    $container->instance(Dispatcher::class, $events = m::mock());
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();
    $events->shouldReceive('until')->with(m::type(NotificationSending::class))->andReturn(true);
    $manager->shouldReceive('driver')->once()->andReturn($driver = m::mock());
    $driver->shouldReceive('send')->once();
    $events->shouldReceive('dispatch')->once()->with(m::type(NotificationSent::class));

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestNotCancelledNotification);
});

test('notification not sent when failed', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Bus::class, $bus = m::mock());
    $container->instance(Dispatcher::class, $events = m::mock());
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $manager->shouldReceive('driver')->andReturn($driver = m::mock());
    $driver->shouldReceive('send')->andThrow(new Exception());
    $events->shouldReceive('listen')->once();
    $events->shouldReceive('until')->with(m::type(NotificationSending::class))->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with(m::type(NotificationFailed::class));
    $events->shouldReceive('dispatch')->never()->with(m::type(NotificationSent::class));

    $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
})->throws(Exception::class);

test('notification failed dispatched only once when failed', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Bus::class, $bus = m::mock());
    $container->instance(Dispatcher::class, $events = m::mock(Dispatcher::class));
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $manager->shouldReceive('driver')->andReturn($driver = m::mock());
    $driver->shouldReceive('send')->andReturnUsing(function ($notifiable, $notification) use ($events) {
        $events->dispatch(new NotificationFailed($notifiable, $notification, 'test'));
        throw new Exception();
    });
    $listeners = new Collection();
    $events->shouldReceive('until')->with(m::type(NotificationSending::class))->andReturn(true);
    $events->shouldReceive('listen')->once()->andReturnUsing(function ($event, $callback) use ($listeners) {
        $listeners->push($callback);
    });
    $events->shouldReceive('dispatch')->once()->with(m::type(NotificationFailed::class))->andReturnUsing(function ($event) use ($listeners) {
        foreach ($listeners as $listener) {
            $listener($event);
        }
    });
    $events->shouldReceive('dispatch')->never()->with(m::type(NotificationSent::class));

    $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
})->throws(Exception::class);

test('notification failed dispatched only once when multiple failed', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Bus::class, $bus = m::mock());
    $container->instance(Dispatcher::class, $events = m::mock(Dispatcher::class));
    Vessel::setInstance($container);
    $manager = $container->make(ChannelManager::class, ['container' => $container]);
    $manager->extend('test', function () use ($events) {
        return new class($events)
        {
            private $count = 0;

            public function __construct(private $events)
            {
            }

            public function send($notifiable, Notification $notification)
            {
                if ($this->count > 1) {
                    throw new \Exception();
                }

                $this->count++;
            }
        };
    });
    $listeners = new Collection();
    $events->shouldReceive('until')->with(m::type(NotificationSending::class))->andReturn(true);
    $events->shouldReceive('listen')->once()->andReturnUsing(function ($event, $callback) use ($listeners) {
        $listeners->push($callback);
    });
    $events->shouldReceive('dispatch')->once()->with(m::type(NotificationFailed::class))->andReturnUsing(function ($event) use ($listeners) {
        foreach ($listeners as $listener) {
            $listener($event);
        }
    });
    $events->shouldReceive('dispatch')->twice()->with(m::type(NotificationSent::class));

    $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
    $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
    $manager->send(new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerTestNotification);
})->throws(Exception::class);

test('notification can be queued', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->with(m::type(SendQueuedNotifications::class));
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestQueuedNotification);
});

test('send queued notifications can be override via container', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->with(m::type(TestSendQueuedNotifications::class));
    $container->bind(SendQueuedNotifications::class, TestSendQueuedNotifications::class);
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $manager->send([new NotificationChannelManagerTestNotifiable], new NotificationChannelManagerTestQueuedNotification);
});

test('queued notification forwards message group from method to queue job', function () {
    $mockedMessageGroupId = 'group-1';

    $notification = $this->getMockBuilder(NotificationChannelManagerTestQueuedNotificationWithMessageGroupMethod::class)->onlyMethods(['messageGroup'])->getMock();
    $notification->expects($this->exactly(2))->method('messageGroup')->willReturn($mockedMessageGroupId);

    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->twice()->withArgs(function ($job) use ($mockedMessageGroupId) {
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $this->assertEquals($mockedMessageGroupId, $job->messageGroup);

        return true;
    });
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);
});

test('queued notification forwards message group from property overriding method to queue job', function () {
    $mockedMessageGroupId = 'group-1';

    // Ensure the messageGroup method is not called when a messageGroup property is provided.
    $notification = $this->getMockBuilder(NotificationChannelManagerTestQueuedNotificationWithMessageGroupMethod::class)->onlyMethods(['messageGroup'])->getMock();
    $notification->expects($this->never())->method('messageGroup')->willReturn('this-should-not-be-used');
    $notification->onGroup($mockedMessageGroupId);

    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->twice()->withArgs(function ($job) use ($mockedMessageGroupId) {
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $this->assertEquals($mockedMessageGroupId, $job->messageGroup);

        return true;
    });
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);
});

test('queued notification forwards message group set to queue job', function () {
    $mockedMessageGroupSet = [
        'test' => 'group-1',
        'test2' => 'group-2',
    ];

    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->twice()->withArgs(function ($job) use ($mockedMessageGroupSet) {
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $this->assertEquals($mockedMessageGroupSet[$job->channels[0]], $job->messageGroup);

        return true;
    });
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithTwoChannels)->onGroup($mockedMessageGroupSet);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);
});

test('queued notification forwards message group set from class to queue job', function () {
    $mockedMessageGroupSet = [
        'test' => 'group-1',
        'test2' => 'group-2',
    ];

    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->twice()->withArgs(function ($job) use ($mockedMessageGroupSet) {
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $this->assertEquals($mockedMessageGroupSet[$job->channels[0]], $job->messageGroup);

        return true;
    });
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithMessageGroups);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);
});

test('queued notification forwards deduplicator to queue job', function () {
    $mockedDeduplicator = fn ($payload, $queue) => 'deduplication-id-1';

    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->once()->withArgs(function ($job) use ($mockedDeduplicator) {
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $this->assertInstanceOf(SerializableClosure::class, $job->deduplicator);
        $this->assertEquals($mockedDeduplicator, $job->deduplicator->getClosure());

        return true;
    });
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $notification = (new NotificationChannelManagerTestQueuedNotification)->withDeduplicator($mockedDeduplicator);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);
});

test('queued notification forwards deduplicator set to queue job', function () {
    $mockedDeduplicatorSet = [
        'test' => fn ($payload, $queue) => 'deduplication-id-1',
        'test2' => fn ($payload, $queue) => 'deduplication-id-2',
    ];

    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->twice()->withArgs(function ($job) use ($mockedDeduplicatorSet) {
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $this->assertInstanceOf(SerializableClosure::class, $job->deduplicator);
        $this->assertEquals($mockedDeduplicatorSet[$job->channels[0]], $job->deduplicator->getClosure());

        return true;
    });
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithTwoChannels)->withDeduplicator($mockedDeduplicatorSet);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);
});

test('queued notification forwards deduplicator set from class to queue job', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->twice()->withArgs(function ($job) {
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $this->assertEquals($job->notification->deduplicatorResults[$job->channels[0]], call_user_func($job->deduplicator, '', null));

        return true;
    });
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithDeduplicators);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);
});

test('queued notification forwards deduplication id method to queue job', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Dispatcher::class, $events = m::mock());
    $container->instance(Bus::class, $bus = m::mock());
    $bus->shouldReceive('dispatch')->twice()->withArgs(function ($job) {
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $this->assertInstanceOf(SerializableClosure::class, $job->deduplicator);
        $this->assertEquals($job->notification->deduplicationId(...), $job->deduplicator->getClosure());

        return true;
    });
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $events->shouldReceive('listen')->once();

    $notification = (new NotificationChannelManagerTestQueuedNotificationWithDeduplicationId);
    $manager->send([new NotificationChannelManagerTestNotifiable], $notification);
});

test('after sending method after sending notification', function () {
    $container = new Vessel;
    $container->instance('config', ['app.name' => 'Name', 'app.logo' => 'Logo']);
    $container->instance(Bus::class, $bus = m::mock());
    $container->instance(Dispatcher::class, $events = m::mock());
    Vessel::setInstance($container);
    $manager = m::mock(ChannelManager::class.'[driver]', [$container]);
    $manager->shouldReceive('driver')->andReturn($driver = m::mock());
    $events->shouldReceive('listen')->once();
    $events->shouldReceive('until')->with(m::type(NotificationSending::class))->andReturn(true);
    $driver->shouldReceive('send')->once()->andReturn($response = m::mock());
    $events->shouldReceive('dispatch')->with(m::type(NotificationSent::class));

    $manager->send($notifiable = new NotificationChannelManagerTestNotifiable, new NotificationChannelManagerWithAfterSendingMethodNotification);

    expect(NotificationChannelManagerWithAfterSendingMethodNotification::$afterSendingNotifiable)->toBe($notifiable)
        ->and(NotificationChannelManagerWithAfterSendingMethodNotification::$afterSendingChannel)->toBe('test')
        ->and(NotificationChannelManagerWithAfterSendingMethodNotification::$afterSendingResponse)->toBe($response);
});

class TestSendQueuedNotifications implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
}

class NotificationChannelManagerTestNotifiable
{
    use Notifiable;
}

class NotificationChannelManagerTestNotification extends Notification
{
    public function via()
    {
        return ['test'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }
}

class NotificationChannelManagerTestNotificationWithTwoChannels extends Notification
{
    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }
}

class NotificationChannelManagerTestCancelledNotification extends Notification
{
    public function via()
    {
        return ['test'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function shouldSend($notifiable, $channel)
    {
        return false;
    }
}

class NotificationChannelManagerTestNotCancelledNotification extends Notification
{
    public function via()
    {
        return ['test'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function shouldSend($notifiable, $channel)
    {
        return true;
    }
}

class NotificationChannelManagerTestQueuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }
}

class NotificationChannelManagerTestQueuedNotificationWithTwoChannels extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }
}

class NotificationChannelManagerTestQueuedNotificationWithMessageGroupMethod extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function messageGroup()
    {
        return 'group-1';
    }
}

class NotificationChannelManagerTestQueuedNotificationWithMessageGroups extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function withMessageGroups($notifiable, $channel)
    {
        return match ($channel) {
            'test' => 'group-1',
            'test2' => 'group-2',
            default => null,
        };
    }
}

class NotificationChannelManagerTestQueuedNotificationWithDeduplicators extends Notification implements ShouldQueue
{
    use Queueable;

    public $deduplicatorResults = [
        'test' => 'deduplication-id-1',
        'test2' => 'deduplication-id-2',
    ];

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function withDeduplicators($notifiable, $channel)
    {
        return match ($channel) {
            'test' => fn ($payload, $queue) => $this->deduplicatorResults['test'],
            'test2' => fn ($payload, $queue) => $this->deduplicatorResults['test2'],
            default => null,
        };
    }
}

class NotificationChannelManagerTestQueuedNotificationWithDeduplicationId extends Notification implements ShouldQueue
{
    use Queueable;

    public function via()
    {
        return ['test', 'test2'];
    }

    public function message()
    {
        return $this->line('test')->action('Text', 'url');
    }

    public function deduplicationId($payload, $queue)
    {
        return 'deduplication-id-1';
    }
}

class NotificationChannelManagerWithAfterSendingMethodNotification extends Notification
{
    public static $afterSendingNotifiable;
    public static $afterSendingChannel;
    public static $afterSendingResponse;

    public function via()
    {
        return ['test'];
    }

    public function afterSending($notifiable, $channel, $response)
    {
        static::$afterSendingNotifiable = $notifiable;
        static::$afterSendingChannel = $channel;
        static::$afterSendingResponse = $response;
    }
}
