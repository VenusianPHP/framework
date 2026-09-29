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
        $this->mergeConfigFrom(__DIR__.'/config/workflows.php', 'workflows');

        $this->app->registerSingleton(AsyncRuntimeManager::class, function ($app) {
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
