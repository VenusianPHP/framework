<?php

use Voyager\Contracts\Database\ModelIdentifier;
use Voyager\Database\Instrument\Model;
use Voyager\Notifications\AnonymousNotifiable;
use Voyager\Notifications\ChannelManager;
use Voyager\Notifications\Notifiable;
use Voyager\Notifications\SendQueuedNotifications;
use Voyager\NutsAndBolts\Collection;
use Mockery as m;

afterEach(function () {
    m::close();
});

test('notifications can be sent', function () {
    $job = new SendQueuedNotifications('notifiables', 'notification');
    $manager = m::mock(ChannelManager::class);
    $manager->shouldReceive('sendNow')->once()->withArgs(function ($notifiables, $notification, $channels) {
        return $notifiables instanceof Collection && $notifiables->toArray() === ['notifiables']
            && $notification === 'notification'
            && $channels === null;
    });
    $job->handle($manager);
});

test('serialization of notifiable model', function () {
    $identifier = new ModelIdentifier(DeferredNotifiableUser::class, [null], [], null);
    $serializedIdentifier = serialize($identifier);

    $job = new SendQueuedNotifications(new DeferredNotifiableUser, 'notification');
    $serialized = serialize($job);

    expect($serialized)->toContain($serializedIdentifier);
});

test('serialization of normal notifiable', function () {
    $notifiable = new AnonymousNotifiable;
    $serializedNotifiable = serialize($notifiable);

    $job = new SendQueuedNotifications($notifiable, 'notification');
    $serialized = serialize($job);

    expect($serialized)->toContain($serializedNotifiable);
});

test('notification can set max exceptions', function () {
    $notifiable = new DeferredNotifiableUser;
    $notification = new class
    {
        public $maxExceptions = 23;
    };

    $job = new SendQueuedNotifications($notifiable, $notification);

    expect($job->maxExceptions)->toEqual(23);
});

class DeferredNotifiableUser extends Model
{
    use Notifiable;

    public $table = 'users';

    public $timestamps = false;
}
