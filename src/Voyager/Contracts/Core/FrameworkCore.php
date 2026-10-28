<?php

namespace Voyager\Contracts\Core;

use ReflectionException;
use RuntimeException;
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
     * Determine if the rocket kernel is running the application.
     *
     * @return bool
     */
    public function isRocketRunning(): bool;

    /**
     * Record that the rocket kernel is running the application. Its bootstrap()
     * calls this before any provider registers.
     *
     * @return void
     */
    public function markRocketRunning(): void;

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

    /**
     * The app's root namespace, read off the psr-4 entry that points at the app directory.
     *
     * @return string
     * @throws RuntimeException
     */
    public function getNamespace(): string;

    /**
     * Get the path to the application "app" directory.
     *
     * @param string $path
     * @return string
     */
    public function path(string $path = ''): string;

    /**
     * Get the path to the bootstrap directory.
     *
     * @param string $path
     * @return string
     */
    public function bootstrapPath(string $path = ''): string;

    /**
     * Get the path to the environment file directory.
     *
     * @return string
     */
    public function environmentPath(): string;

    /**
     * Get the environment file the application is using.
     *
     * @return string
     */
    public function environmentFile(): string;

    /**
     * Get the fully qualified path to the environment file.
     *
     * @return string
     */
    public function environmentFilePath(): string;

    /**
     * Set the environment file to be loaded during bootstrapping.
     *
     * @param string $file
     * @return $this
     */
    public function loadEnvironmentFrom(string $file): static;

    /**
     * Detect the application's current environment.
     *
     * @param callable $callback
     * @return string
     */
    public function detectEnvironment(callable $callback): string;

    /**
     * Set the callback which determines the current vessel environment.
     *
     * @param callable|null $callback
     * @return void
     */
    public function resolveEnvironmentUsing(?callable $callback): void;

    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedConfigPath(): string;

    /**
     * Get the path to the signals cache file.
     *
     * @return string
     */
    public function getCachedSignalsPath(): string;

    /**
     * Determine if the application configuration is cached.
     *
     * @return bool|null
     * @throws ReflectionException
     */
    public function configurationIsCached(): ?bool;

    /**
     * Determine if the application signals are cached.
     *
     * @return bool
     * @throws ReflectionException
     */
    public function signalsAreCached(): bool;

    /**
     * Whether the framework's own config should be merged under the app's.
     *
     * @return bool
     */
    public function shouldMergeFrameworkConfiguration(): bool;

    /**
     * Get the path to the service provider list in the bootstrap directory.
     *
     * @return string
     */
    public function getBootstrapProvidersPath(): string;

    /**
     * Every registered provider that is an instance of the given one.
     *
     * @param string|ServiceProvider $provider
     * @return array<class-string, ServiceProvider>
     */
    public function getProviders(string|ServiceProvider $provider): array;

    /**
     * Add an array of services to the application's deferred services.
     *
     * @param array $services
     * @return void
     */
    public function addDeferredServices(array $services): void;

    /**
     * Load and boot every remaining deferred provider.
     *
     * @return void
     * @throws ReflectionException
     */
    public function loadDeferredProviders(): void;

    /**
     * Terminate the application.
     *
     * @return void
     * @throws ReflectionException
     */
    public function terminate(): void;
}