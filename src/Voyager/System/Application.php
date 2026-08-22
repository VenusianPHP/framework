<?php

namespace Voyager\System;

use Closure;
use Composer\Autoload\ClassLoader;
use Voyager\System\Configuration\ApplicationBuilder;
use Voyager\Vessel\Vessel;
use Voyager\Contracts\Console\Kernel as ConsoleKernelContract;
use Voyager\Contracts\Sketches\Kernel as SketchKernelContract;
use Voyager\Contracts\System\Application as ApplicationContract;
use Voyager\Contracts\System\CachesConfiguration;
use Voyager\Contracts\System\MaintenanceMode as MaintenanceModeContract;
use Voyager\Events\EventServiceProvider;
use Voyager\Filesystem\Filesystem;
use Voyager\System\Bootstrap\LoadEnvironmentVariables;
use Voyager\System\Events\LocaleUpdated;
use Voyager\Log\Context\ContextServiceProvider;
use Voyager\Log\LogServiceProvider;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Env;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\Concerns\Macroable;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

use function Voyager\Filesystem\join_paths;

class Application extends Vessel implements ApplicationContract, CachesConfiguration
{
    use Macroable;

    /**
     * The Venusian framework version.
     *
     * @var string
     */
    const string VERSION = '0.8.0';

    /**
     * The base path for the Venusian installation.
     *
     * @var string
     */
    protected ?string $basePath = null;

    /**
     * The array of registered callbacks.
     *
     * @var callable[]
     */
    protected array $registeredCallbacks = [];

    /**
     * Indicates if the application has been bootstrapped before.
     *
     * @var bool
     */
    protected bool $hasBeenBootstrapped = false;

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected bool $booted = false;

    /**
     * The array of booting callbacks.
     *
     * @var callable[]
     */
    protected array $bootingCallbacks = [];

    /**
     * The array of booted callbacks.
     *
     * @var callable[]
     */
    protected array $bootedCallbacks = [];

    /**
     * The array of terminating callbacks.
     *
     * @var callable[]
     */
    protected array $terminatingCallbacks = [];

    /**
     * Every registered service provider.
     *
     * @var array<string, ServiceProvider>
     */
    protected array $serviceProviders = [];

    /**
     * The names of the loaded service providers.
     *
     * @var array
     */
    protected array $loadedProviders = [];

    /**
     * The deferred services and their providers.
     *
     * @var array
     */
    protected array $deferredServices = [];

    /**
     * The custom bootstrap path defined by the developer.
     *
     * @var string
     */
    protected ?string $bootstrapPath = null;

    /**
     * The custom application path defined by the developer.
     *
     * @var string
     */
    protected ?string $appPath = null;

    /**
     * The custom configuration path defined by the developer.
     *
     * @var string
     */
    protected ?string $configPath = null;

    /**
     * The custom database path defined by the developer.
     *
     * @var string
     */
    protected ?string $databasePath = null;

    /**
     * The custom language file path defined by the developer.
     *
     * @var string
     */
    protected ?string $langPath = null;

    /**
     * The custom storage path defined by the developer.
     *
     * @var string
     */
    protected ?string $storagePath = null;

    /**
     * The custom environment path defined by the developer.
     *
     * @var string
     */
    protected ?string $environmentPath = null;

    /**
     * The environment file to load during bootstrapping.
     *
     * @var string
     */
    protected string $environmentFile = '.env';

    /**
     * Indicates if the application is running in the console.
     *
     * @var bool|null
     */
    protected ?bool $isRunningInConsole = null;

    /**
     * The application namespace.
     *
     * @var string
     */
    protected ?string $namespace = null;

    /**
     * Indicates if the framework's base configuration should be merged.
     *
     * @var bool
     */
    protected bool $mergeFrameworkConfiguration = true;

    /**
     * The prefixes of absolute cache paths for use during normalization.
     *
     * @var string[]
     */
    protected array $absoluteCachePathPrefixes = ['/', '\\'];

    /**
     * Create a new Voyager application instance.
     *
     * @param  string|null  $basePath
     */
    public function __construct(?string $basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();
    }

    /**
     * Begin configuring a new Venusian application instance.
     *
     * @param  string|null  $basePath
     * @return ApplicationBuilder
     */
    public static function configure(?string $basePath = null): ApplicationBuilder
    {
        $basePath = match (true) {
            is_string($basePath) => $basePath,
            default => static::inferBasePath(),
        };

        return new ApplicationBuilder(new static($basePath))
            ->withKernels()
            ->withEvents()
            ->withCommands()
            ->withProviders();
    }

    /**
     * Infer the application's base directory from the environment.
     *
     * @return string
     */
    public static function inferBasePath(): string
    {
        return match (true) {
            isset($_ENV['APP_BASE_PATH']) => $_ENV['APP_BASE_PATH'],
            isset($_SERVER['APP_BASE_PATH']) => $_SERVER['APP_BASE_PATH'],
            default => dirname(array_values(array_filter(
                array_keys(ClassLoader::getRegisteredLoaders()),
                fn ($path) => ! str_starts_with($path, 'phar://'),
            ))[0]),
        };
    }

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Register the basic bindings into the container.
     *
     * @return void
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);

        $this->instance(Vessel::class, $this);

        $this->singleton(PackageManifest::class, fn () => new PackageManifest(
            new Filesystem, $this->basePath(), $this->getCachedPackagesPath()
        ));
    }

    /**
     * Register every base service provider.
     *
     * @return void
     */
    protected function registerBaseServiceProviders(): void
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new ContextServiceProvider($this));
    }

    /**
     * Run the given array of bootstrap classes.
     *
     * @param  string[]  $bootstrappers
     * @return void
     */
    public function bootstrapWith(array $bootstrappers): void
    {
        $this->hasBeenBootstrapped = true;

        foreach ($bootstrappers as $bootstrapper) {
            $this['events']->dispatch('bootstrapping: '.$bootstrapper, [$this]);

            $this->make($bootstrapper)->bootstrap($this);

            $this['events']->dispatch('bootstrapped: '.$bootstrapper, [$this]);
        }
    }

    /**
     * Register a callback to run after loading the environment.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function afterLoadingEnvironment(Closure $callback): void
    {
        $this->afterBootstrapping(
            LoadEnvironmentVariables::class, $callback
        );
    }

    /**
     * Register a callback to run before a bootstrapper.
     *
     * @param string $bootstrapper
     * @param  Closure  $callback
     * @return void
     */
    public function beforeBootstrapping(string $bootstrapper, Closure $callback): void
    {
        $this['events']->listen('bootstrapping: '.$bootstrapper, $callback);
    }

    /**
     * Register a callback to run after a bootstrapper.
     *
     * @param string $bootstrapper
     * @param  \Closure  $callback
     * @return void
     */
    public function afterBootstrapping(string $bootstrapper, Closure $callback): void
    {
        $this['events']->listen('bootstrapped: '.$bootstrapper, $callback);
    }

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->hasBeenBootstrapped;
    }

    /**
     * Set the base path for the application.
     *
     * @param  string  $basePath
     * @return $this
     */
    public function setBasePath(string $basePath): static
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->bindPathsInContainer();

        return $this;
    }

    /**
     * Bind every application path in the container.
     *
     * @return void
     */
    protected function bindPathsInContainer(): void
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.config', $this->configPath());
        $this->instance('path.database', $this->databasePath());
        $this->instance('path.storage', $this->storagePath());

        $this->useBootstrapPath(value(function () {
            return is_dir($directory = $this->basePath('.venusian'))
                ? $directory
                : $this->basePath('bootstrap');
        }));

        $this->useLangPath(value(function () {
            return $this->basePath('lang');
        }));
    }

    /**
     * Get the path to the application "app" directory.
     *
     * @param  string  $path
     * @return string
     */
    public function path(string $path = ''): string
    {
        return $this->joinPaths($this->appPath ?: $this->basePath('app'), $path);
    }

    /**
     * Set the application directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function useAppPath(string $path): static
    {
        $this->appPath = $path;

        $this->instance('path', $path);

        return $this;
    }

    /**
     * Get the base path of the Venusian installation.
     *
     * @param  string  $path
     * @return string
     */
    public function basePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, $path);
    }

    /**
     * Get the path to the bootstrap directory.
     *
     * @param  string  $path
     * @return string
     */
    public function bootstrapPath(string $path = ''): string
    {
        return $this->joinPaths($this->bootstrapPath, $path);
    }

    /**
     * Get the path to the service provider list in the bootstrap directory.
     *
     * @return string
     */
    public function getBootstrapProvidersPath(): string
    {
        return $this->bootstrapPath('providers.php');
    }

    /**
     * Set the bootstrap file directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function useBootstrapPath(string $path): static
    {
        $this->bootstrapPath = $path;

        $this->instance('path.bootstrap', $path);

        return $this;
    }

    /**
     * Get the path to the application configuration files.
     *
     * @param  string  $path
     * @return string
     */
    public function configPath(string $path = ''): string
    {
        return $this->joinPaths($this->configPath ?: $this->basePath('config'), $path);
    }

    /**
     * Set the configuration directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function useConfigPath(string $path): static
    {
        $this->configPath = $path;

        $this->instance('path.config', $path);

        return $this;
    }

    /**
     * Get the path to the database directory.
     *
     * @param  string  $path
     * @return string
     */
    public function databasePath(string $path = ''): string
    {
        return $this->joinPaths($this->databasePath ?: $this->basePath('database'), $path);
    }

    /**
     * Set the database directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function useDatabasePath(string $path): static
    {
        $this->databasePath = $path;

        $this->instance('path.database', $path);

        return $this;
    }

    /**
     * Get the path to the language files.
     *
     * @param  string  $path
     * @return string
     */
    public function langPath(string $path = ''): string
    {
        return $this->joinPaths($this->langPath, $path);
    }

    /**
     * Set the language file directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function useLangPath(string $path): static
    {
        $this->langPath = $path;

        $this->instance('path.lang', $path);

        return $this;
    }


    /**
     * Get the path to the storage directory.
     *
     * @param  string  $path
     * @return string
     */
    public function storagePath(string $path = ''): string
    {
        if (isset($_ENV['LARAVEL_STORAGE_PATH'])) {
            return $this->joinPaths($this->storagePath ?: $_ENV['LARAVEL_STORAGE_PATH'], $path);
        }

        if (isset($_SERVER['LARAVEL_STORAGE_PATH'])) {
            return $this->joinPaths($this->storagePath ?: $_SERVER['LARAVEL_STORAGE_PATH'], $path);
        }

        return $this->joinPaths($this->storagePath ?: $this->basePath('storage'), $path);
    }

    /**
     * Set the storage directory.
     *
     * @param  string  $path
     * @return $this
     */
    public function useStoragePath(string $path): static
    {
        $this->storagePath = $path;

        $this->instance('path.storage', $path);

        return $this;
    }

    /**
     * Join the given paths together.
     *
     * @param  string|null  $basePath
     * @param  string  $path
     * @return string
     */
    public function joinPaths(?string $basePath, string $path = ''): string
    {
        return join_paths($basePath, $path);
    }

    /**
     * Get the path to the environment file directory.
     *
     * @return string
     */
    public function environmentPath(): string
    {
        return $this->environmentPath ?: $this->basePath;
    }

    /**
     * Set the directory for the environment file.
     *
     * @param  string  $path
     * @return $this
     */
    public function useEnvironmentPath(string $path): static
    {
        $this->environmentPath = $path;

        return $this;
    }

    /**
     * Set the environment file to be loaded during bootstrapping.
     *
     * @param  string  $file
     * @return $this
     */
    public function loadEnvironmentFrom(string $file): static
    {
        $this->environmentFile = $file;

        return $this;
    }

    /**
     * Get the environment file the application is using.
     *
     * @return string
     */
    public function environmentFile(): string
    {
        return $this->environmentFile ?: '.env';
    }

    /**
     * Get the fully-qualified path to the environment file.
     *
     * @return string
     */
    public function environmentFilePath(): string
    {
        return $this->environmentPath().DIRECTORY_SEPARATOR.$this->environmentFile();
    }

    /**
     * Get or check the current application environment.
     *
     * @param  string|array  ...$environments
     * @return string|bool
     */
    public function environment(string|array ...$environments): string|bool
    {
        if (count($environments) > 0) {
            $patterns = is_array($environments[0]) ? $environments[0] : $environments;

            return Str::is($patterns, $this['env']);
        }

        return $this['env'];
    }

    /**
     * Determine if the application is in the local environment.
     *
     * @return bool
     */
    public function isLocal(): bool
    {
        return $this['env'] === 'local';
    }

    /**
     * Determine if the application is in the production environment.
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this['env'] === 'production';
    }

    /**
     * Detect the application's current environment.
     *
     * @param  \Closure  $callback
     * @return string
     */
    public function detectEnvironment(Closure $callback): string
    {
        $args = $this->runningInConsole() && isset($_SERVER['argv'])
            ? $_SERVER['argv']
            : null;

        return $this['env'] = (new EnvironmentDetector)->detect($callback, $args);
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole(): bool
    {
        if ($this->isRunningInConsole === null) {
            $this->isRunningInConsole = Env::get('APP_RUNNING_IN_CONSOLE') ?? (\PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg');
        }

        return $this->isRunningInConsole;
    }

    /**
     * Determine if the application is running any of the given console commands.
     *
     * @param  string|array  ...$commands
     * @return bool
     */
    public function runningConsoleCommand(string|array ...$commands): bool
    {
        if (! $this->runningInConsole()) {
            return false;
        }

        return in_array(
            $_SERVER['argv'][1] ?? null,
            is_array($commands[0]) ? $commands[0] : $commands
        );
    }

    /**
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests(): bool
    {
        return $this->bound('env') && $this['env'] === 'testing';
    }

    /**
     * Determine if the application is running with debug mode enabled.
     *
     * @return bool
     */
    public function hasDebugModeEnabled(): bool
    {
        return (bool) $this['config']->get('app.debug');
    }

    /**
     * Register a new registered listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function registered(callable $callback): void
    {
        $this->registeredCallbacks[] = $callback;
    }

    /**
     * Register every configured provider.
     *
     * @return void
     */
    public function registerConfiguredProviders(): void
    {
        $providers = (new Collection($this->make('config')->get('app.providers')))
            ->partition(fn ($provider) => str_starts_with($provider, 'Voyager\\'));

        $providers->splice(1, 0, [$this->make(PackageManifest::class)->providers()]);

        (new ProviderRepository($this, new Filesystem, $this->getCachedServicesPath()))
            ->load($providers->collapse()->toArray());

        $this->fireAppCallbacks($this->registeredCallbacks);
    }

    /**
     * Register a service provider with the application.
     *
     * @param ServiceProvider|string  $provider
     * @param  bool  $force
     * @return ServiceProvider
     */
    public function register(ServiceProvider|string $provider, bool $force = false): ServiceProvider
    {
        if (($registered = $this->getProvider($provider)) && ! $force) {
            return $registered;
        }

        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        // If there are bindings / singletons set as properties on the provider we
        // will spin through them and register them with the application, which
        // serves as a convenience layer while registering a lot of bindings.
        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $key = is_int($key) ? $value : $key;

                $this->singleton($key, $value);
            }
        }

        $this->markAsRegistered($provider);

        // If the application has already booted, we will call this boot method on
        // the provider class so it has an opportunity to do its boot logic and
        // will be ready for any usage by this developer's application logic.
        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param ServiceProvider|string  $provider
     * @return ServiceProvider|null
     */
    public function getProvider(ServiceProvider|string $provider): ?ServiceProvider
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return $this->serviceProviders[$name] ?? null;
    }

    /**
     * Get the registered service provider instances if any exist.
     *
     * @param ServiceProvider|string  $provider
     * @return array
     */
    public function getProviders(ServiceProvider|string $provider): array
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return Arr::where($this->serviceProviders, fn ($value) => $value instanceof $name);
    }

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string  $provider
     * @return ServiceProvider
     */
    public function resolveProvider(string $provider): ServiceProvider
    {
        return new $provider($this);
    }

    /**
     * Mark the given provider as registered.
     *
     * @param ServiceProvider $provider
     * @return void
     */
    protected function markAsRegistered(ServiceProvider $provider): void
    {
        $class = get_class($provider);

        $this->serviceProviders[$class] = $provider;

        $this->loadedProviders[$class] = true;
    }

    /**
     * Load and boot every remaining deferred provider.
     *
     * @return void
     */
    public function loadDeferredProviders(): void
    {
        // We will simply spin through each of the deferred providers and register each
        // one and boot them if the application has booted. This should make each of
        // the remaining services available to this application for immediate use.
        foreach ($this->deferredServices as $service => $provider) {
            $this->loadDeferredProvider($service);
        }

        $this->deferredServices = [];
    }

    /**
     * Load the provider for a deferred service.
     *
     * @param  string  $service
     * @return void
     */
    public function loadDeferredProvider(string $service): void
    {
        if (! $this->isDeferredService($service)) {
            return;
        }

        $provider = $this->deferredServices[$service];

        // If the service provider has not already been loaded and registered we can
        // register it with the application and remove the service from this list
        // of deferred services, since it will already be loaded on subsequent.
        if (! isset($this->loadedProviders[$provider])) {
            $this->registerDeferredProvider($provider, $service);
        }
    }

    /**
     * Register a deferred provider and service.
     *
     * @param  string  $provider
     * @param  string|null  $service
     * @return void
     */
    public function registerDeferredProvider(string $provider, ?string $service = null): void
    {
        // Once the provider that provides the deferred service has been registered we
        // will remove it from our local list of the deferred services with related
        // providers so that this container does not try to resolve it out again.
        if ($service) {
            unset($this->deferredServices[$service]);
        }

        $this->register($instance = new $provider($this));

        if (! $this->isBooted()) {
            $this->booting(function () use ($instance) {
                $this->bootProvider($instance);
            });
        }
    }

    /**
     * Resolve the given type from the container.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>  $abstract
     * @param  array  $parameters
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     *
     * @throws \Voyager\Contracts\Container\BindingResolutionException
     */
    public function make($abstract, array $parameters = []): mixed
    {
        $this->loadDeferredProviderIfNeeded($abstract = $this->getAlias($abstract));

        return parent::make($abstract, $parameters);
    }

    /**
     * Resolve the given type from the container.
     *
     * @template TClass of object
     *
     * @param  string|class-string<TClass>|callable  $abstract
     * @param  array  $parameters
     * @param  bool  $raiseEvents
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     *
     * @throws \Voyager\Contracts\Container\BindingResolutionException
     * @throws \Voyager\Contracts\Container\CircularDependencyException
     */
    protected function resolve($abstract, $parameters = [], $raiseEvents = true)
    {
        $this->loadDeferredProviderIfNeeded($abstract = $this->getAlias($abstract));

        return parent::resolve($abstract, $parameters, $raiseEvents);
    }

    /**
     * Load the deferred provider if the given type is a deferred service and the instance has not been loaded.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function loadDeferredProviderIfNeeded(string $abstract): void
    {
        if ($this->isDeferredService($abstract) && ! isset($this->instances[$abstract])) {
            $this->loadDeferredProvider($abstract);
        }
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return $this->isDeferredService($abstract) || parent::bound($abstract);
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        // Once the application has booted we will also fire some "booted" callbacks
        // for any listeners that need to do work after this initial booting gets
        // finished. This is useful when ordering the boot-up processes we run.
        $this->fireAppCallbacks($this->bootingCallbacks);

        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });

        $this->booted = true;

        $this->fireAppCallbacks($this->bootedCallbacks);
    }

    /**
     * Boot the given service provider.
     *
     * @param ServiceProvider $provider
     * @return void
     */
    protected function bootProvider(ServiceProvider $provider): void
    {
        $provider->callBootingCallbacks();

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }

        $provider->callBootedCallbacks();
    }

    /**
     * Register a new boot listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booting(callable $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booted(callable $callback): void
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted()) {
            $callback($this);
        }
    }

    /**
     * Call the booting callbacks for the application.
     *
     * @param  callable[]  $callbacks
     * @return void
     */
    protected function fireAppCallbacks(array &$callbacks): void
    {
        $index = 0;

        while ($index < count($callbacks)) {
            $callbacks[$index]($this);

            $index++;
        }
    }

    /**
     * Handle the incoming Computer command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return int
     */
    public function handleCommand(InputInterface $input): int
    {
        $kernel = $this->make(ConsoleKernelContract::class);

        $status = $kernel->handle(
            $input,
            new ConsoleOutput
        );

        $kernel->terminate($input, $status);

        return $status;
    }

    /**
     * Handle the incoming Runner (sketch) console input.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @return int
     */
    public function handleSketch(InputInterface $input): int
    {
        $kernel = $this->make(SketchKernelContract::class);

        $status = $kernel->handle(
            $input,
            new ConsoleOutput
        );

        $kernel->terminate($input, $status);

        return $status;
    }

    /**
     * Determine if the framework's base configuration should be merged.
     *
     * @return bool
     */
    public function shouldMergeFrameworkConfiguration(): bool
    {
        return $this->mergeFrameworkConfiguration;
    }

    /**
     * Indicate that the framework's base configuration should not be merged.
     *
     * @return $this
     */
    public function dontMergeFrameworkConfiguration(): static
    {
        $this->mergeFrameworkConfiguration = false;

        return $this;
    }

    /**
     * Get the path to the cached services.php file.
     *
     * @return string
     */
    public function getCachedServicesPath(): string
    {
        return $this->normalizeCachePath('APP_SERVICES_CACHE', 'cache/services.php');
    }

    /**
     * Get the path to the cached packages.php file.
     *
     * @return string
     */
    public function getCachedPackagesPath(): string
    {
        return $this->normalizeCachePath('APP_PACKAGES_CACHE', 'cache/packages.php');
    }

    /**
     * Determine if the application configuration is cached.
     *
     * @return bool
     */
    public function configurationIsCached(): bool
    {
        if ($this->bound('config_loaded_from_cache')) {
            return (bool) $this->make('config_loaded_from_cache');
        }

        return $this->instance('config_loaded_from_cache', is_file($this->getCachedConfigPath()));
    }

    /**
     * Get the path to the configuration cache file.
     *
     * @return string
     */
    public function getCachedConfigPath(): string
    {
        return $this->normalizeCachePath('APP_CONFIG_CACHE', 'cache/config.php');
    }

    /**
     * Determine if the application events are cached.
     *
     * @return bool
     */
    public function eventsAreCached(): bool
    {
        if ($this->bound('events.cached')) {
            return (bool) $this->make('events.cached');
        }

        return $this->instance(
            'events.cached', $this['files']->exists($this->getCachedEventsPath())
        );
    }

    /**
     * Get the path to the events cache file.
     *
     * @return string
     */
    public function getCachedEventsPath(): string
    {
        return $this->normalizeCachePath('APP_EVENTS_CACHE', 'cache/events.php');
    }

    /**
     * Normalize a relative or absolute path to a cache file.
     *
     * @param  string  $key
     * @param  string  $default
     * @return string
     */
    protected function normalizeCachePath(string $key, string $default): string
    {
        if (is_null($env = Env::get($key))) {
            return $this->bootstrapPath($default);
        }

        return Str::startsWith($env, $this->absoluteCachePathPrefixes)
            ? $env
            : $this->basePath($env);
    }

    /**
     * Add new prefix to list of absolute path prefixes.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function addAbsoluteCachePathPrefix(string $prefix): static
    {
        $this->absoluteCachePathPrefixes[] = $prefix;

        return $this;
    }

    /**
     * Get an instance of the maintenance mode manager implementation.
     *
     * @return \Voyager\Contracts\System\MaintenanceMode
     */
    public function maintenanceMode(): MaintenanceModeContract
    {
        return $this->make(MaintenanceModeContract::class);
    }

    /**
     * Determine if the application is currently down for maintenance.
     *
     * @return bool
     */
    public function isDownForMaintenance(): bool
    {
        return $this->maintenanceMode()->active();
    }

    /**
     * Register a terminating callback with the application.
     *
     * @param  callable|string  $callback
     * @return $this
     */
    public function terminating(callable|string $callback): static
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate(): void
    {
        $index = 0;

        while ($index < count($this->terminatingCallbacks)) {
            $this->call($this->terminatingCallbacks[$index]);

            $index++;
        }
    }

    /**
     * Get the service providers that have been loaded.
     *
     * @return array<string, bool>
     */
    public function getLoadedProviders()
    {
        return $this->loadedProviders;
    }

    /**
     * Determine if the given service provider is loaded.
     *
     * @param  string  $provider
     * @return bool
     */
    public function providerIsLoaded(string $provider): bool
    {
        return isset($this->loadedProviders[$provider]);
    }

    /**
     * Get the application's deferred services.
     *
     * @return array
     */
    public function getDeferredServices(): array
    {
        return $this->deferredServices;
    }

    /**
     * Set the application's deferred services.
     *
     * @param  array  $services
     * @return void
     */
    public function setDeferredServices(array $services): void
    {
        $this->deferredServices = $services;
    }

    /**
     * Determine if the given service is a deferred service.
     *
     * @param  string  $service
     * @return bool
     */
    public function isDeferredService(string $service): bool
    {
        return isset($this->deferredServices[$service]);
    }

    /**
     * Add an array of services to the application's deferred services.
     *
     * @param  array  $services
     * @return void
     */
    public function addDeferredServices(array $services): void
    {
        $this->deferredServices = array_merge($this->deferredServices, $services);
    }

    /**
     * Remove an array of services from the application's deferred services.
     *
     * @param  array  $services
     * @return void
     */
    public function removeDeferredServices(array $services): void
    {
        foreach ($services as $service) {
            unset($this->deferredServices[$service]);
        }
    }

    /**
     * Configure the real-time magic alias namespace.
     *
     * @param  string  $namespace
     * @return void
     */
    public function provideMagicAliases(string $namespace): void
    {
        AliasLoader::setMagicAliasNamespace($namespace);
    }

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this['config']->get('app.locale');
    }

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function currentLocale(): string
    {
        return $this->getLocale();
    }

    /**
     * Get the current application fallback locale.
     *
     * @return string
     */
    public function getFallbackLocale(): string
    {
        return $this['config']->get('app.fallback_locale');
    }

    /**
     * Set the current application locale.
     *
     * @param  string  $locale
     * @return void
     */
    public function setLocale(string $locale): void
    {
        $previous = $this['config']->get('app.locale');

        $this['config']->set('app.locale', $locale);

        $this['translator']->setLocale($locale);

        $this['events']->dispatch(new LocaleUpdated($locale, $previous));
    }

    /**
     * Set the current application fallback locale.
     *
     * @param  string  $fallbackLocale
     * @return void
     */
    public function setFallbackLocale(string $fallbackLocale): void
    {
        $this['config']->set('app.fallback_locale', $fallbackLocale);

        $this['translator']->setFallback($fallbackLocale);
    }

    /**
     * Determine if the application locale is the given locale.
     *
     * @param  string  $locale
     * @return bool
     */
    public function isLocale(string $locale): bool
    {
        return $this->getLocale() == $locale;
    }

    /**
     * Register the core class aliases in the container.
     *
     * @return void
     */
    public function registerCoreContainerAliases(): void
    {
        foreach ([
            'app' => [self::class, \Voyager\Contracts\Vessel\Vessel::class, \Voyager\Contracts\System\Application::class, \Psr\Container\ContainerInterface::class],
            'auth' => [\Voyager\Auth\AuthManager::class, \Voyager\Contracts\Auth\Factory::class],
            'auth.driver' => [\Voyager\Contracts\Auth\Guard::class],
            'auth.password' => [\Voyager\Auth\Passwords\PasswordBrokerManager::class, \Voyager\Contracts\Auth\PasswordBrokerFactory::class],
            'auth.password.broker' => [\Voyager\Auth\Passwords\PasswordBroker::class, \Voyager\Contracts\Auth\PasswordBroker::class],
            'cache' => [\Voyager\Cache\CacheManager::class, \Voyager\Contracts\Cache\Factory::class],
            'cache.store' => [\Voyager\Cache\Repository::class, \Voyager\Contracts\Cache\Repository::class, \Psr\SimpleCache\CacheInterface::class],
            'cache.psr6' => [\Symfony\Component\Cache\Adapter\Psr16Adapter::class, \Symfony\Component\Cache\Adapter\AdapterInterface::class, \Psr\Cache\CacheItemPoolInterface::class],
            'config' => [\Voyager\Config\Repository::class, \Voyager\Contracts\Config\Repository::class],
            'db' => [\Voyager\Database\DatabaseManager::class, \Voyager\Database\ConnectionResolverInterface::class],
            'db.connection' => [\Voyager\Database\Connection::class, \Voyager\Database\ConnectionInterface::class],
            'db.schema' => [\Voyager\Database\Schema\Builder::class],
            'encrypter' => [\Voyager\Encryption\Encrypter::class, \Voyager\Contracts\Encryption\Encrypter::class, \Voyager\Contracts\Encryption\StringEncrypter::class],
            'events' => [\Voyager\Events\Dispatcher::class, \Voyager\Contracts\Events\Dispatcher::class],
            'files' => [\Voyager\Filesystem\Filesystem::class],
            'filesystem' => [\Voyager\Filesystem\FilesystemManager::class, \Voyager\Contracts\Filesystem\Factory::class],
            'filesystem.disk' => [\Voyager\Contracts\Filesystem\Filesystem::class],
            'filesystem.cloud' => [\Voyager\Contracts\Filesystem\Cloud::class],
            'hash' => [\Voyager\Hashing\HashManager::class],
            'hash.driver' => [\Voyager\Contracts\Hashing\Hasher::class],
            'log' => [\Voyager\Log\LogManager::class, \Psr\Log\LoggerInterface::class],
            'mail.manager' => [\Voyager\Mail\MailManager::class, \Voyager\Contracts\Mail\Factory::class],
            'mailer' => [\Voyager\Mail\Mailer::class, \Voyager\Contracts\Mail\Mailer::class, \Voyager\Contracts\Mail\MailQueue::class],
            'queue' => [\Voyager\Queue\QueueManager::class, \Voyager\Contracts\Queue\Factory::class, \Voyager\Contracts\Queue\Monitor::class],
            'queue.connection' => [\Voyager\Contracts\Queue\Queue::class],
            'queue.failer' => [\Voyager\Queue\Failed\FailedJobProviderInterface::class],
            'redis' => [\Voyager\Redis\RedisManager::class, \Voyager\Contracts\Redis\Factory::class],
            'redis.connection' => [\Voyager\Redis\Connections\Connection::class, \Voyager\Contracts\Redis\Connection::class],
            'translator' => [\Voyager\Translation\Translator::class, \Voyager\Contracts\Translation\Translator::class],
            'validator' => [\Voyager\Validation\Factory::class, \Voyager\Contracts\Validation\Factory::class],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush(): void
    {
        parent::flush();

        $this->buildStack = [];
        $this->loadedProviders = [];
        $this->bootedCallbacks = [];
        $this->bootingCallbacks = [];
        $this->deferredServices = [];
        $this->reboundCallbacks = [];
        $this->serviceProviders = [];
        $this->resolvingCallbacks = [];
        $this->terminatingCallbacks = [];
        $this->beforeResolvingCallbacks = [];
        $this->afterResolvingCallbacks = [];
        $this->globalBeforeResolvingCallbacks = [];
        $this->globalResolvingCallbacks = [];
        $this->globalAfterResolvingCallbacks = [];
    }

    /**
     * Get the application namespace.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function getNamespace(): string
    {
        if (! is_null($this->namespace)) {
            return $this->namespace;
        }

        $composer = json_decode(file_get_contents($this->basePath('composer.json')), true);

        foreach ((array) data_get($composer, 'autoload.psr-4') as $namespace => $path) {
            foreach ((array) $path as $pathChoice) {
                if (realpath($this->path()) === realpath($this->basePath($pathChoice))) {
                    return $this->namespace = $namespace;
                }
            }
        }

        throw new RuntimeException('Unable to detect application namespace.');
    }
}
