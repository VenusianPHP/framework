<?php

use Carbon\Carbon;
use Voyager\Notifications\Channels\DatabaseChannel;
use Voyager\Notifications\Messages\DatabaseMessage;
use Voyager\Notifications\Notification;
use Mockery as m;

test('database channel creates database record with proper data', function () {
    $notification = new NotificationDatabaseChannelTestNotification;
    $notification->id = 1;
    $notifiable = m::mock();
    $notifiable->shouldReceive('routeNotificationFor')
        ->once()
        ->with('database', $notification)
        ->andReturn($repository = m::mock());
    $repository->shouldReceive('create')->once()->with([
        'id' => 1,
        'type' => get_class($notification),
        'data' => ['invoice_id' => 1],
        'read_at' => null,
    ]);

    $channel = new DatabaseChannel;
    $channel->send($notifiable, $notification);
});

test('correct payload is sent to database', function () {
    $notification = new NotificationDatabaseChannelTestNotification;
    $notification->id = 1;
    $notifiable = m::mock();
    $notifiable->shouldReceive('routeNotificationFor')
        ->once()
        ->with('database', $notification)
        ->andReturn($repository = m::mock());
    $repository->shouldReceive('create')->once()->with([
        'id' => 1,
        'type' => get_class($notification),
        'data' => ['invoice_id' => 1],
        'read_at' => null,
        'something' => 'else',
    ]);

    $channel = new ExtendedDatabaseChannel;
    $channel->send($notifiable, $notification);
});

test('customize type is sent to database', function () {
    $notification = new NotificationDatabaseChannelCustomizeTypeTestNotification;
    $notification->id = 1;
    $notifiable = m::mock();
    $notifiable->shouldReceive('routeNotificationFor')
        ->once()
        ->with('database', $notification)
        ->andReturn($repository = m::mock());
    $repository->shouldReceive('create')->once()->with([
        'id' => 1,
        'type' => 'MONTHLY',
        'data' => ['invoice_id' => 1],
        'read_at' => Carbon::now()->toDateTimeString(),
        'something' => 'else',
    ]);

    $channel = new ExtendedDatabaseChannel;
    $channel->send($notifiable, $notification);
});

class NotificationDatabaseChannelTestNotification extends Notification
{
    public function toDatabase($notifiable)
    {
        return new DatabaseMessage(['invoice_id' => 1]);
    }
}

class NotificationDatabaseChannelCustomizeTypeTestNotification extends Notification
{
    public function toDatabase($notifiable)
    {
        return new DatabaseMessage(['invoice_id' => 1]);
    }

    public function databaseType()
    {
        return 'MONTHLY';
    }

    public function initialDatabaseReadAtValue()
    {
        return Carbon::now();
    }
}

class ExtendedDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, Notification $notification)
    {
        return array_merge(parent::buildPayload($notifiable, $notification), [
            'something' => 'else',
        ]);
    }
}
