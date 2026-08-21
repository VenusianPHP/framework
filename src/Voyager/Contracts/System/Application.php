<?php

namespace Voyager\Contracts\System;

use Voyager\Contracts\Vessel\Vessel;

interface Application extends Vessel
{
    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version();

    /**
     * Get the base path of the Venusian installation.
     *
     * @param  string  $path
     * @return string
     */
    public function basePath(string $path = '');

    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path
     * @return string
     */
    public function bootstrapPath(string $path = '');

    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path
     * @return string
     */
    public function configPath(string $path = '');

    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     * @return string
     */
    public function databasePath(string $path = '');

    /**
     * Get the path to the language files.
     *
     * @param  string  $path
     * @return string
     */
    public function langPath(string $path = '');

    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     * @return string
     */
    public function storagePath(string $path = '');

    /**
     * Get or check the current application environment.
     *
     * @param  string|array  ...$environments
     * @return string|bool
     */
    public function environment(string|array ...$environments);

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole();

    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests();

    /**
     * Determine if the application is running with debug mode enabled.
     *
     * @return bool
     */
    public function hasDebugModeEnabled();

    /**
     * Get an instance of the maintenance mode manager implementation.
     *
     * @return \Voyager\Contracts\System\MaintenanceMode
     */
    public function maintenanceMode();

    /**
     * Determine if the application is currently down for maintenance.
     *
     * @return bool
     */
    public function isDownForMaintenance();

    /**
     * Register every configured provider.
     *
     * @return void
     */
    public function registerConfiguredProviders();

    /**
     * Register a service provider with the application.
     *
     * @param  \Voyager\NutsAndBolts\ServiceProvider|string  $provider
     * @param  bool  $force
     * @return \Voyager\NutsAndBolts\ServiceProvider
     */
    public function register(\Voyager\NutsAndBolts\ServiceProvider|string $provider, bool $force = false);

    /**
     * Register a deferred provider and service.
     *
     * @param  string  $provider
     * @param  string|null  $service
     * @return void
     */
    public function registerDeferredProvider(string $provider, ?string $service = null);

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     * @return \Voyager\NutsAndBolts\ServiceProvider
     */
    public function resolveProvider(string $provider);

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot();

    /**
     * Register a new boot listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booting(callable $callback);

    /**
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booted(callable $callback);

    /**
     * Run the given array of bootstrap classes.
     *
     * @param  array  $bootstrappers
     * @return void
     */
    public function bootstrapWith(array $bootstrappers);

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function getLocale();

    /**
     * Get the application namespace.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getNamespace();

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param  \Voyager\NutsAndBolts\ServiceProvider|string  $provider
     * @return array
     */
    public function getProviders(\Voyager\NutsAndBolts\ServiceProvider|string $provider);

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped();

    /**
     * Load and boot every remaining deferred provider.
     *
     * @return void
     */
    public function loadDeferredProviders();

    /**
     * Set the current application locale.
     *
     * @param  string  $locale
     * @return void
     */
    public function setLocale(string $locale);

    /**
     * Register a terminating callback with the application.
     *
     * @param  callable|string  $callback
     * @return \Voyager\Contracts\System\Application
     */
    public function terminating(callable|string $callback);

    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate();
}
