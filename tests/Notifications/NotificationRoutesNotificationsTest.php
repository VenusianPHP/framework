<?php

use Voyager\Vessel\Vessel;
use Voyager\Contracts\Notifications\Dispatcher;
use Voyager\Notifications\RoutesNotifications;
use Voyager\NutsAndBolts\MagicAliases\Notification;
use Mockery as m;

afterEach(function () {
    Vessel::setInstance(null);
});

test('notification can be dispatched', function () {
    $container = new Vessel;
    $factory = m::mock(Dispatcher::class);
    $container->instance(Dispatcher::class, $factory);
    $notifiable = new RoutesNotificationsTestInstance;
    $instance = new stdClass;
    $factory->shouldReceive('send')->once()->with($notifiable, $instance);
    Vessel::setInstance($container);

    $notifiable->notify($instance);
});

test('notification can be sent now', function () {
    $container = new Vessel;
    $factory = m::mock(Dispatcher::class);
    $container->instance(Dispatcher::class, $factory);
    $notifiable = new RoutesNotificationsTestInstance;
    $instance = new stdClass;
    $factory->shouldReceive('sendNow')->once()->with($notifiable, $instance, null);
    Vessel::setInstance($container);

    $notifiable->notifyNow($instance);
});

test('notification option routing', function () {
    $instance = new RoutesNotificationsTestInstance;

    expect($instance->routeNotificationFor('foo'))->toBe('bar')
        ->and($instance->routeNotificationFor('mail'))->toBe('taylor@laravel.com');
});

test('on demand notifications cannot use database channel', function () {
    Notification::route('database', 'foo');
})->throws(InvalidArgumentException::class, 'The database channel does not support on-demand notifications.');

class RoutesNotificationsTestInstance
{
    use RoutesNotifications;

    protected $email = 'taylor@laravel.com';

    public function routeNotificationForFoo()
    {
        return 'bar';
    }
}
