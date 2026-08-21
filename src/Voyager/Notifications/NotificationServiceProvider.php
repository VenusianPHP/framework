<?php

namespace Voyager\Notifications;

use Voyager\Contracts\Notifications\Dispatcher as DispatcherContract;
use Voyager\Contracts\Notifications\Factory as FactoryContract;
use Voyager\NutsAndBolts\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    // Laravel boots a Blade view namespace here and publishes the mail
    // notification template. Mail and the view layer are out of scope for this
    // port, so there is nothing to boot.

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ChannelManager::class, fn ($app) => new ChannelManager($app));

        $this->app->alias(
            ChannelManager::class, DispatcherContract::class
        );

        $this->app->alias(
            ChannelManager::class, FactoryContract::class
        );
    }
}
