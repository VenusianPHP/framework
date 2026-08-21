<?php

namespace Voyager\Notifications;

use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Contracts\Translation\HasLocalePreference;
use Voyager\Database\Instrument\Collection as InstrumentCollection;
use Voyager\Database\Instrument\Model;
use Voyager\Notifications\Events\NotificationFailed;
use Voyager\Notifications\Events\NotificationSending;
use Voyager\Notifications\Events\NotificationSent;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\Concerns\Localizable;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Throwable;

class NotificationSender
{
    use Localizable;

    /**
     * The notification manager instance.
     *
     * @var \Voyager\Notifications\ChannelManager
     */
    protected $manager;

    /**
     * The Bus dispatcher instance.
     *
     * @var \Voyager\Contracts\Bus\Dispatcher
     */
    protected $bus;

    /**
     * The event dispatcher.
     *
     * @var \Voyager\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The locale to be used when sending notifications.
     *
     * @var string|null
     */
    protected $locale;

    /**
     * Indicates whether a NotificationFailed event has been dispatched.
     *
     * @var bool
     */
    protected $failedEventWasDispatched = false;

    /**
     * Create a new notification sender instance.
     *
     * @param  \Voyager\Notifications\ChannelManager  $manager
     * @param  \Voyager\Contracts\Bus\Dispatcher  $bus
     * @param  \Voyager\Contracts\Events\Dispatcher  $events
     * @param  string|null  $locale
     */
    public function __construct($manager, $bus, $events, $locale = null)
    {
        $this->bus = $bus;
        $this->events = $events;
        $this->locale = $locale;
        $this->manager = $manager;

        $this->events->listen(NotificationFailed::class, fn () => $this->failedEventWasDispatched = true);
    }

    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param  \Voyager\NutsAndBolts\Collection|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    public function send($notifiables, $notification)
    {
        if ($notification instanceof ShouldQueue) {
            return $this->queueNotification($notifiables, $notification);
        }

        $this->sendNow($notifiables, $notification);
    }

    /**
     * Send the given notification immediately.
     *
     * @param  \Voyager\NutsAndBolts\Collection|mixed  $notifiables
     * @param  mixed  $notification
     * @param  array|null  $channels
     * @return void
     */
    public function sendNow($notifiables, $notification, ?array $channels = null)
    {
        $notifiables = $this->formatNotifiables($notifiables);

        $original = clone $notification;

        foreach ($notifiables as $notifiable) {
            if (empty($viaChannels = $channels ?: $original->via($notifiable))) {
                continue;
            }

            $this->withLocale($this->preferredLocale($notifiable, $original), function () use ($viaChannels, $notifiable, $original) {
                $notificationId = (string) Str::uuid();

                foreach ((array) $viaChannels as $channel) {
                    if (! ($notifiable instanceof AnonymousNotifiable && $channel === 'database')) {
                        $this->sendToNotifiable($notifiable, $notificationId, clone $original, $channel);
                    }
                }
            });
        }
    }

    /**
     * Get the notifiable's preferred locale for the notification.
     *
     * @param  mixed  $notifiable
     * @param  mixed  $notification
     * @return string|null
     */
    protected function preferredLocale($notifiable, $notification)
    {
        return $notification->locale ?? $this->locale ?? value(function () use ($notifiable) {
            if ($notifiable instanceof HasLocalePreference) {
                return $notifiable->preferredLocale();
            }
        });
    }

    /**
     * Send the given notification to the given notifiable via a channel.
     *
     * @param  mixed  $notifiable
     * @param  string  $id
     * @param  mixed  $notification
     * @param  string  $channel
     * @return void
     *
     * @throws \Throwable
     */
    protected function sendToNotifiable($notifiable, $id, $notification, $channel)
    {
        if (! $notification->id) {
            $notification->id = $id;
        }

        if (! $this->shouldSendNotification($notifiable, $notification, $channel)) {
            return;
        }

        try {
            $response = $this->manager->driver($channel)->send($notifiable, $notification);
        } catch (Throwable $exception) {
            if (! $this->failedEventWasDispatched) {
                if ($exception instanceof HttpTransportException) {
                    $exception = new TransportException($exception->getMessage(), $exception->getCode());
                }

                $this->events->dispatch(
                    new NotificationFailed($notifiable, $notification, $channel, ['exception' => $exception])
                );
            }

            $this->failedEventWasDispatched = false;

            throw $exception;
        }

        if (method_exists($notification, 'afterSending')) {
            $notification->afterSending($notifiable, $channel, $response);
        }

        $this->events->dispatch(
            new NotificationSent($notifiable, $notification, $channel, $response)
        );
    }

    /**
     * Determines if the notification can be sent.
     *
     * @param  mixed  $notifiable
     * @param  mixed  $notification
     * @param  string  $channel
     * @return bool
     */
    protected function shouldSendNotification($notifiable, $notification, $channel)
    {
        if (method_exists($notification, 'shouldSend') &&
            $notification->shouldSend($notifiable, $channel) === false) {
            return false;
        }

        return $this->events->until(
            new NotificationSending($notifiable, $notification, $channel)
        ) !== false;
    }

    /**
     * Queue the given notification instances.
     *
     * @param  mixed  $notifiables
     * @param  \Voyager\Notifications\Notification  $notification
     * @return void
     */
    protected function queueNotification($notifiables, $notification)
    {
        $notifiables = $this->formatNotifiables($notifiables);

        $original = clone $notification;

        foreach ($notifiables as $notifiable) {
            $notificationId = (string) Str::uuid();

            foreach ((array) $original->via($notifiable) as $channel) {
                $notification = clone $original;

                if (! $notification->id) {
                    $notification->id = $notificationId;
                }

                if (! is_null($this->locale)) {
                    $notification->locale = $this->locale;
                }

                $connection = $notification->connection;

                if (method_exists($notification, 'viaConnections')) {
                    $connection = $notification->viaConnections()[$channel] ?? $connection;
                }

                $queue = $notification->queue;

                if (method_exists($notification, 'viaQueues')) {
                    $queue = $notification->viaQueues()[$channel] ?? $queue;
                }

                $delay = $notification->delay;

                if (method_exists($notification, 'withDelay')) {
                    $delay = $notification->withDelay($notifiable, $channel) ?? null;
                }

                $messageGroup = $notification->messageGroup ?? (method_exists($notification, 'messageGroup') ? $notification->messageGroup() : null);

                if (method_exists($notification, 'withMessageGroups')) {
                    $messageGroup = $notification->withMessageGroups($notifiable, $channel) ?? null;
                }

                $deduplicator = $notification->deduplicator ?? (method_exists($notification, 'deduplicationId') ? $notification->deduplicationId(...) : null);

                if (method_exists($notification, 'withDeduplicators')) {
                    $deduplicator = $notification->withDeduplicators($notifiable, $channel) ?? null;
                }

                $middleware = $notification->middleware ?? [];

                if (method_exists($notification, 'middleware')) {
                    $middleware = array_merge(
                        $notification->middleware($notifiable, $channel),
                        $middleware
                    );
                }

                $this->bus->dispatch(
                    $this->manager->getContainer()->make(SendQueuedNotifications::class, [
                        'notifiables' => $notifiable,
                        'notification' => $notification,
                        'channels' => [$channel],
                    ])
                        ->onConnection($connection)
                        ->onQueue($queue)
                        ->delay(is_array($delay) ? ($delay[$channel] ?? null) : $delay)
                        ->onGroup(is_array($messageGroup) ? ($messageGroup[$channel] ?? null) : $messageGroup)
                        ->withDeduplicator(is_array($deduplicator) ? ($deduplicator[$channel] ?? null) : $deduplicator)
                        ->through($middleware)
                );
            }
        }
    }

    /**
     * Format the notifiables into a Collection / array if necessary.
     *
     * @param  mixed  $notifiables
     * @return \Voyager\Database\Instrument\Collection|array
     */
    protected function formatNotifiables($notifiables)
    {
        if (! $notifiables instanceof Collection && ! is_array($notifiables)) {
            return $notifiables instanceof Model
                ? new InstrumentCollection([$notifiables])
                : [$notifiables];
        }

        return $notifiables;
    }
}
