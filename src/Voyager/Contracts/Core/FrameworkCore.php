<?php

namespace Voyager\Contracts\Core;

use Voyager\Contracts\Vessel\TheServiceContainer;
use Voyager\NutsAndBolts\ServiceProvider;

interface FrameworkCore extends TheServiceContainer
{
    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version(): string;

    /**
     * Get the base path of the Laravel installation.
     *
     * @param  string  $path
     * @return string
     */
    public function basePath(string $path = ''): string;

    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path
     * @return string
     */
    public function configPath(string $path = ''): string;

    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     * @return string
     */
    public function databasePath(string $path = ''): string;

    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     * @return string
     */
    public function storagePath(string $path = ''): string;

    /**
     * Register a service provider with the application.
     *
     * @param string|ServiceProvider $provider
     * @param bool $force
     * @return ServiceProvider
     */
    public function register(string|ServiceProvider $provider, bool $force = false): ServiceProvider;

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param string $provider
     * @return ServiceProvider
     */
    public function resolveProvider(string $provider): ServiceProvider;

    /**
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booted(callable $callback): void;

    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests(): bool;

    /**
     * Register every configured provider.
     *
     * @return void
     */
    public function registerConfiguredProviders(): void;

    /**
     * Run the given array of bootstrap classes.
     *
     * @param  array  $bootstrappers
     * @return void
     */
    public function bootstrapWith(array $bootstrappers): void;

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped(): bool;

    /**
     * Get or check the current application environment.
     *
     * @param  string|array  ...$environments
     * @return string|bool
     */
    public function environment(string|array ...$environments): bool|string;


}