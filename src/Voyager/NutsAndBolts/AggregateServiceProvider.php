<?php

namespace Voyager\NutsAndBolts;

class AggregateServiceProvider extends ServiceProvider
{
    /**
     * The provider class names.
     *
     * @var array<int, class-string<\Voyager\NutsAndBolts\ServiceProvider>>
     */
    protected array $providers = [];

    /**
     * An array of the service provider instances.
     *
     * @var array<int, \Voyager\NutsAndBolts\ServiceProvider>
     */
    protected array $instances = [];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->instances = [];

        foreach ($this->providers as $provider) {
            $this->instances[] = $this->app->register($provider);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        $provides = [];

        foreach ($this->providers as $provider) {
            $instance = $this->app->resolveProvider($provider);

            $provides = array_merge($provides, $instance->provides());
        }

        return $provides;
    }
}
