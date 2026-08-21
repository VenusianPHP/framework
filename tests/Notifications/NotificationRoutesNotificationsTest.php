<?php

namespace Tests\Notifications;

use Voyager\Vessel\Vessel;
use Voyager\Contracts\Notifications\Dispatcher;
use Voyager\Notifications\RoutesNotifications;
use Voyager\NutsAndBolts\MagicAliases\Notification;
use InvalidArgumentException;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use stdClass;

class NotificationRoutesNotificationsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        Vessel::setInstance(null);

        parent::tearDown();
    }

    public function testNotificationCanBeDispatched()
    {
        $container = new Vessel;
        $factory = m::mock(Dispatcher::class);
        $container->instance(Dispatcher::class, $factory);
        $notifiable = new RoutesNotificationsTestInstance;
        $instance = new stdClass;
        $factory->shouldReceive('send')->once()->with($notifiable, $instance);
        Vessel::setInstance($container);

        $notifiable->notify($instance);
    }

    public function testNotificationCanBeSentNow()
    {
        $container = new Vessel;
        $factory = m::mock(Dispatcher::class);
        $container->instance(Dispatcher::class, $factory);
        $notifiable = new RoutesNotificationsTestInstance;
        $instance = new stdClass;
        $factory->shouldReceive('sendNow')->once()->with($notifiable, $instance, null);
        Vessel::setInstance($container);

        $notifiable->notifyNow($instance);
    }

    public function testNotificationOptionRouting()
    {
        $instance = new RoutesNotificationsTestInstance;
        $this->assertSame('bar', $instance->routeNotificationFor('foo'));
        $this->assertSame('taylor@laravel.com', $instance->routeNotificationFor('mail'));
    }

    public function testOnDemandNotificationsCannotUseDatabaseChannel()
    {
        $this->expectExceptionObject(
            new InvalidArgumentException('The database channel does not support on-demand notifications.')
        );

        Notification::route('database', 'foo');
    }
}

class RoutesNotificationsTestInstance
{
    use RoutesNotifications;

    protected $email = 'taylor@laravel.com';

    public function routeNotificationForFoo()
    {
        return 'bar';
    }
}
