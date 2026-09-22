<?php

namespace Voyager\Core\Providers;

use Voyager\NutsAndBolts\Composer;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Contracts\NutsAndBolts\DeferrableProvider;

class ComposerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     * @throws \ReflectionException
     */
    public function register(): void
    {
        $this->app->registerSingleton('composer', function ($app) {
            return new Composer($app['files'], $app->basePath());
        });

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['composer'];
    }
}
