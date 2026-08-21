<?php

namespace Voyager\Testing;

use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Testing\Concerns\TestCaches;
use Voyager\Testing\Concerns\TestDatabases;

class ParallelTestingServiceProvider extends ServiceProvider implements DeferrableProvider
{
    use TestCaches, TestDatabases;

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->bootTestCache();
            $this->bootTestDatabase();
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->app->singleton(ParallelTesting::class, function () {
                return new ParallelTesting($this->app);
            });
        }
    }
}
