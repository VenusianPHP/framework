<?php

namespace Voyager\Workflows;

use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\NutsAndBolts\ServiceProvider;

class WorkflowsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AsyncRuntimeManager::class, function ($app) {
            return new AsyncRuntimeManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            AsyncRuntimeManager::class,
        ];
    }
}
