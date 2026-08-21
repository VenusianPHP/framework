<?php

namespace Voyager\Pipeline;

use Voyager\Contracts\Pipeline\Hub as PipelineHubContract;
use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\NutsAndBolts\ServiceProvider;

class PipelineServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(
            PipelineHubContract::class,
            Hub::class
        );

        $this->app->bind('pipeline', fn ($app) => new Pipeline($app));
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            PipelineHubContract::class,
            'pipeline',
        ];
    }
}
