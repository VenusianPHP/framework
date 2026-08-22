<?php

namespace Voyager\Workflows;

use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\NutsAndBolts\ServiceProvider;

class WorkflowsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(AsyncRuntimeManager::class, function ($app) {
            return new AsyncRuntimeManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return list<class-string>
     */
    public function provides(): array
    {
        return [
            AsyncRuntimeManager::class,
        ];
    }
}
