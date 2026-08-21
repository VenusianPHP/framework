<?php

use Voyager\Broadcasting\PrivateChannel;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Notifications\Channels\BroadcastChannel;
use Voyager\Notifications\Events\BroadcastNotificationCreated;
use Voyager\Notifications\Messages\BroadcastMessage;
use Voyager\Notifications\Notification;
use Mockery as m;

test('database channel creates database record with proper data', function () {
    $notification = new NotificationBroadcastChannelTestNotification;
    $notification->id = 1;
    $notifiable = m::mock();

    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->with(m::type(BroadcastNotificationCreated::class));
    $channel = new BroadcastChannel($events);
    $channel->send($notifiable, $notification);
});

test('notification is broadcasted on custom channels', function () {
    $notification = new CustomChannelsTestNotification;
    $notification->id = 1;
    $notifiable = m::mock();

    $event = new BroadcastNotificationCreated(
        $notifiable, $notification, $notification->toArray($notifiable)
    );

    $channels = $event->broadcastOn();

    expect($channels[0])->toEqual(new PrivateChannel('custom-channel'));
});

test('notification is broadcasted with custom event name', function () {
    $notification = new CustomEventNameTestNotification;
    $notification->id = 1;
    $notifiable = m::mock();

    $event = new BroadcastNotificationCreated(
        $notifiable, $notification, $notification->toArray($notifiable)
    );

    $eventName = $event->broadcastType();

    expect($eventName)->toBe('custom.type');
});

test('notification is broadcasted with custom data type', function () {
    $notification = new CustomEventNameTestNotification;
    $notification->id = 1;
    $notifiable = m::mock();

    $event = new BroadcastNotificationCreated(
        $notifiable, $notification, $notification->toArray($notifiable)
    );

    $data = $event->broadcastWith();

    expect($data['type'])->toBe('custom.type');
});

test('notification is broadcasted now', function () {
    $notification = new TestNotificationBroadCastedNow;
    $notification->id = 1;
    $notifiable = m::mock();

    $events = m::mock(Dispatcher::class);
    $events->shouldReceive('dispatch')->once()->with(m::on(function ($event) {
        return $event->connection === 'sync';
    }));
    $channel = new BroadcastChannel($events);
    $channel->send($notifiable, $notification);
});

test('notification is broadcasted with custom additional payload', function () {
    $notification = new CustomBroadcastWithTestNotification;
    $notification->id = 1;
    $notifiable = m::mock();

    $event = new BroadcastNotificationCreated(
        $notifiable, $notification, $notification->toArray($notifiable)
    );

    $data = $event->broadcastWith();

    expect($data)->toHaveKey('additional');
});

class NotificationBroadcastChannelTestNotification extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }
}

class CustomChannelsTestNotification extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }

    public function broadcastOn()
    {
        return [new PrivateChannel('custom-channel')];
    }
}

class CustomEventNameTestNotification extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }

    public function broadcastType()
    {
        return 'custom.type';
    }
}

class TestNotificationBroadCastedNow extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }

    public function toBroadcast()
    {
        return (new BroadcastMessage([]))->onConnection('sync');
    }
}

class CustomBroadcastWithTestNotification extends Notification
{
    public function toArray($notifiable)
    {
        return ['invoice_id' => 1];
    }

    public function broadcastWith()
    {
        return ['id' => 1, 'type' => 'custom', 'additional' => 'custom'];
    }
}
