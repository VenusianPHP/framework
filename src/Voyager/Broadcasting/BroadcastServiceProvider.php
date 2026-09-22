<?php

namespace Voyager\Broadcasting;

use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\NutsAndBolts\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/broadcasting.php', 'broadcasting');

        $this->app->registerSingleton('broadcast', fn ($app) => new BroadcastManager($app));

        $this->app->registerSingleton('broadcast.connection', function ($app) {
            return $app->make('broadcast')->connection();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'broadcast',
            'broadcast.connection',
        ];
    }
}
