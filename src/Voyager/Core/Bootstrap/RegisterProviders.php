<?php

namespace Voyager\Core\Bootstrap;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Core\DefaultProviders;
use Voyager\Core\RenderedInstance;

class RegisterProviders
{
    /**
     * The service providers that should be merged before registration.
     *
     * @var array
     */
    protected static array $merge = [];

    /**
     * The path to the bootstrap provider configuration file.
     *
     * @var string|null
     */
    protected static ?string $bootstrap_provider_path = null;

    /**
     * Bootstrap the given application.
     *
     * @param FrameworkCore $app
     * @return void
     * @throws ReflectionException
     */
    public function bootstrap(FrameworkCore $app): void
    {
        if (! $app->isBound('config_loaded_from_cache') ||
            $app->make('config_loaded_from_cache') === false) {
            $this->mergeAdditionalProviders($app);
        }

        $app->registerConfiguredProviders();

    }

    /**
     * Merge the additional configured providers into the configuration.
     *
     * @param RenderedInstance $app
     * @throws ReflectionException
     */
    protected function mergeAdditionalProviders(RenderedInstance $app): void
    {
        if (static::$bootstrap_provider_path &&
            file_exists(static::$bootstrap_provider_path)) {
            $packageProviders = require static::$bootstrap_provider_path;

            foreach ($packageProviders as $index => $provider) {
                if (! class_exists($provider)) {
                    unset($packageProviders[$index]);
                }
            }
        }

        $app->make('config')->set(
            'app.providers',
            array_merge(
                $app->make('config')->get('app.providers') ?? DefaultProviders::make()->toArray(),
                static::$merge,
                array_values($packageProviders ?? []),
            ),
        );
    }

    /**
     * Merge the given providers into the provider configuration before registration.
     *
     * @param  array  $providers
     * @param  string|null  $bootstrap_provider_path
     * @return void
     */
    public static function merge(array $providers, ?string $bootstrap_provider_path = null): void
    {
        static::$bootstrap_provider_path = $bootstrap_provider_path;

        static::$merge = array_values(array_filter(array_unique(
            array_merge(static::$merge, $providers)
        )));
    }
}