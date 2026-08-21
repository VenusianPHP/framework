<?php

namespace Voyager\Notifications;

use Voyager\Contracts\Bus\Dispatcher as Bus;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Contracts\Notifications\Dispatcher as DispatcherContract;
use Voyager\Contracts\Notifications\Factory as FactoryContract;
use Voyager\NutsAndBolts\Manager;
use Voyager\NutsAndBolts\Concerns\Macroable;
use InvalidArgumentException;

class ChannelManager extends Manager implements DispatcherContract, FactoryContract
{
    use Macroable;

    /**
     * The resolved notification sender instance.
     *
     * @var \Voyager\Notifications\NotificationSender|null
     */
    protected $notificationSender;

    /**
     * The default channel used to deliver messages.
     *
     * Laravel defaults this to "mail". Mail is out of scope for this port, so
     * the default is the database channel instead.
     *
     * @var string
     */
    protected $defaultChannel = 'database';

    /**
     * The locale used when sending notifications.
     *
     * @var string|null
     */
    protected $locale;

    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param  \Voyager\NutsAndBolts\Collection|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    public function send($notifiables, $notification)
    {
        $this->resolveNotificationSender()->send($notifiables, $notification);
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
        $this->resolveNotificationSender()->sendNow($notifiables, $notification, $channels);
    }

    /**
     * Get a channel instance.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function channel($name = null)
    {
        return $this->driver($name);
    }

    /**
     * Create an instance of the database driver.
     *
     * @return \Voyager\Notifications\Channels\DatabaseChannel
     */
    protected function createDatabaseDriver()
    {
        return $this->vessel->make(Channels\DatabaseChannel::class);
    }

    /**
     * Create an instance of the broadcast driver.
     *
     * @return \Voyager\Notifications\Channels\BroadcastChannel
     */
    protected function createBroadcastDriver()
    {
        return $this->vessel->make(Channels\BroadcastChannel::class);
    }


    // Laravel also registers a mail channel here. Mail is out of scope for
    // this port — it renders through Blade, which is the web surface this
    // port exists to shed — so the channel goes with it.


    /**
     * Create a new driver instance.
     *
     * @param  string  $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function createDriver($driver)
    {
        try {
            return parent::createDriver($driver);
        } catch (InvalidArgumentException $e) {
            if (class_exists($driver)) {
                return $this->vessel->make($driver);
            }

            throw $e;
        }
    }

    /**
     * Resolve the NotificationSender instance.
     *
     * @return \Voyager\Notifications\NotificationSender
     */
    protected function resolveNotificationSender()
    {
        return $this->notificationSender ??= new NotificationSender(
            $this, $this->vessel->make(Bus::class), $this->vessel->make(Dispatcher::class), $this->locale
        );
    }

    /**
     * Get the default channel driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->defaultChannel;
    }

    /**
     * Get the default channel driver name.
     *
     * @return string
     */
    public function deliversVia()
    {
        return $this->getDefaultDriver();
    }

    /**
     * Set the default channel driver name.
     *
     * @param  string  $channel
     * @return void
     */
    public function deliverVia($channel)
    {
        $this->defaultChannel = $channel;
    }

    /**
     * Set the locale of notifications.
     *
     * @param  string  $locale
     * @return $this
     */
    public function locale($locale)
    {
        $this->locale = $locale;

        return $this;
    }
}
