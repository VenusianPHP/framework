<?php

namespace Voyager\Core;

use Exception;
use RuntimeException;
use ReflectionException;
use Voyager\Filesystem\Filesystem;
use Voyager\Vessel\ControlPanel;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\Concerns\Macroable;
use Voyager\Core\Concerns\BasePathManagement;
use Voyager\Contracts\Core\CachesConfiguration;
use Voyager\Core\Concerns\InstanceBootstrapping;
use Voyager\Contracts\Vessel\DataBindingException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputInterface;
use Voyager\Contracts\Console\Kernel as ConsoleKernel;
use Voyager\Contracts\Sketches\Kernel as SketchKernel;

class RenderedInstance extends ControlPanel implements FrameworkCore, CachesConfiguration
{
    use Macroable;
    use BasePathManagement;
    use InstanceBootstrapping;

    /**
     * The Venusian framework version.
     *
     * @var string
     */
    const string VERSION = '0.9.0';

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected bool $booted = false;

    /**
     * Indicates if the application has been bootstrapped before.
     *
     * @var bool
     */
    protected bool $has_been_bootstrapped = false;

    /**
     * The environment file to load during bootstrapping.
     *
     * @var string
     */
    protected string $environment_file = '.env';

    /**
     * The custom environment path defined by the developer.
     *
     * @var string
     */
    protected string $environment_path = "";

    /**
     * @throws ReflectionException
     */
    public function __construct(
        ?string $base_path = null
    ) {
        if($base_path) {
            $this->setBasePath($base_path);
        }

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();
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
     * Handle the incoming Artisan command.
     *
     * @param InputInterface $input
     * @return int
     * @throws ReflectionException
     */
    public function handleInquiry(InputInterface $input): int
    {
        $kernel = $this->make(ConsoleKernel::class);

        $results = $kernel->handle(
            $input,
            new ConsoleOutput
        );

        $kernel->terminate($input, $results);

        return $results;
    }

    public function handleSketch(InputInterface $input): int
    {
        $kernel = $this->make(SketchKernel::class);

        $results = $kernel->handle($input, new ConsoleOutput);

        $kernel->terminate($input, $results);

        return $results;
    }

    /**
     * Register a service provider with the application.
     *
     * @param string|ServiceProvider $provider
     * @param bool $force
     * @return ServiceProvider
     * @throws ReflectionException
     */
    public function register(string|ServiceProvider $provider, bool $force = false): ServiceProvider
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

                $this->registerSingleton($key, $value);
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
     * Register a new "booted" listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booted(callable $callback): void
    {
        $this->booted_callbacks[] = $callback;

        if ($this->isBooted()) {
            $callback($this);
        }
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
     * Determine if the application is running unit tests.
     *
     * @return bool
     */
    public function runningUnitTests(): bool
    {
        return $this->isBound('env') && $this['env'] === 'testing';
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
     * Get the path to the cached services.php file.
     *
     * @return string
     */
    public function getCachedServicesPath(): string
    {
        return $this->normalizeCachePath('APP_SERVICES_CACHE', 'cache/services.php');
    }


    /**
     * Register every configured provider.
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     */
    public function registerConfiguredProviders(): void
    {

        $providers = new Collection($this->make('config')->get('app.providers'))
            ->partition(fn ($provider) => str_starts_with($provider, 'Voyager\\'));

        $providers->splice(1, 0, [$this->make(PackageManifest::class)->providers()]);


        $repo = new ProviderRepository($this, new Filesystem, $this->getCachedServicesPath());
        $repo->load($providers->collapse()->toArray());

        $this->fireAppCallbacks($this->registered_callbacks);

    }

    /**
     * Add an array of services to the application's deferred services.
     *
     * @param  array  $services
     * @return void
     */
    public function addDeferredServices(array $services): void
    {
        $this->deferred_services = array_merge($this->deferred_services, $services);
    }

    /**
     * Boot the application's service providers.
     *
     * @return void
     * @throws ReflectionException
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        // Once the application has booted we will also fire some "booted" callbacks
        // for any listeners that need to do work after this initial booting gets
        // finished. This is useful when ordering the boot-up processes we run.
        $this->fireAppCallbacks($this->booting_callbacks);

        array_walk($this->service_providers, function ($p) {
            $this->bootProvider($p);
        });

        $this->booted = true;

        $this->fireAppCallbacks($this->booted_callbacks);
    }

    /**
     * Run the given array of bootstrap classes.
     *
     * @param string[] $bootstrappers
     * @return void
     * @throws ReflectionException
     */
    public function bootstrapWith(array $bootstrappers): void
    {
        $this->has_been_bootstrapped = true;

        foreach ($bootstrappers as $bootstrapper) {
            $this['signals']->dispatch('bootstrapping: '.$bootstrapper, [$this]);

            $this->make($bootstrapper)->bootstrap($this);

            $this['signals']->dispatch('bootstrapped: '.$bootstrapper, [$this]);
        }
    }

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->has_been_bootstrapped;
    }

    /**
     * Terminate the application.
     *
     * @return void
     * @throws ReflectionException
     */
    public function terminate(): void
    {
        $index = 0;

        while ($index < count($this->terminating_callbacks)) {
            $this->call($this->terminating_callbacks[$index]);

            $index++;
        }
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
     * Resolve the given type from the container.
     *
     * @template TClass of object
     *
     * @param string|class-string<TClass> $abstract
     * @param  array  $parameters
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     *
     * @throws DataBindingException|ReflectionException
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        $this->loadDeferredProviderIfNeeded($abstract = $this->getAlias($abstract));

        return parent::make($abstract, $parameters);
    }

    /**
     * Determine if the given service is a deferred service.
     *
     * @param  string  $service
     * @return bool
     */
    public function isDeferredService(string $service): bool
    {
        return isset($this->deferred_services[$service]);
    }

    /**
     * Load and boot every remaining deferred provider.
     *
     * @return void
     * @throws ReflectionException
     */
    public function loadDeferredProviders(): void
    {
        // We will simply spin through each of the deferred providers and register each
        // one and boot them if the application has booted. This should make each of
        // the remaining services available to this application for immediate use.
        foreach ($this->deferred_services as $service => $provider) {
            $this->loadDeferredProvider($service);
        }

        $this->deferred_services = [];
    }

    /**
     * Load the provider for a deferred service.
     *
     * @param string $service
     * @return void
     * @throws ReflectionException
     */
    public function loadDeferredProvider(string $service): void
    {
        if (! $this->isDeferredService($service)) {
            return;
        }

        $provider = $this->deferred_services[$service];

        // If the service provider has not already been loaded and registered we can
        // register it with the application and remove the service from this list
        // of deferred services, since it will already be loaded on subsequent.
        if (! isset($this->loaded_providers[$provider])) {
            $this->registerDeferredProvider($provider, $service);
        }
    }

    /**
     * Register a deferred provider and service.
     *
     * @param string $provider
     * @param string|null $service
     * @return void
     * @throws ReflectionException
     */
    public function registerDeferredProvider(string $provider, ?string $service = null): void
    {
        // Once the provider that provides the deferred service has been registered we
        // will remove it from our local list of the deferred services with related
        // providers so that this container does not try to resolve it out again.
        if ($service) {
            unset($this->deferred_services[$service]);
        }

        $this->register($instance = new $provider($this));

        if (! $this->isBooted()) {
            $this->booting(function () use ($instance) {
                $this->bootProvider($instance);
            });
        }
    }

    /**
     * Register a new boot listener.
     *
     * @param  callable  $callback
     * @return void
     */
    public function booting(callable $callback): void
    {
        $this->booting_callbacks[] = $callback;
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
     * @throws ReflectionException
     */
    public function configurationIsCached(): ?bool
    {
        if ($this->isBound('config_loaded_from_cache')) {
            return (bool) $this->make('config_loaded_from_cache');
        }

        $this->registerInstance('config_loaded_from_cache', is_file($this->getCachedConfigPath()));

        return $this->make('config_loaded_from_cache');
    }

    /**
     * Get the environment file the application is using.
     *
     * @return string
     */
    public function environmentFile(): string
    {
        return $this->environment_file ?: '.env';
    }

    /**
     * Get the path to the environment file directory.
     *
     * @return string
     */
    public function environmentPath(): string
    {
        return $this->environment_path ?: $this->base_path;
    }

    /**
     * Get the fully qualified path to the environment file.
     *
     * @return string
     */
    public function environmentFilePath(): string
    {
        return $this->environmentPath().DIRECTORY_SEPARATOR.$this->environmentFile();
    }

    /**
     * Set the environment file to be loaded during bootstrapping.
     *
     * @param string $file
     * @return $this
     */
    public function loadEnvironmentFrom(string $file): static
    {
        $this->environment_file = $file;

        return $this;
    }

    /**
     * Detect the application's current environment.
     *
     * @param  callable  $callback
     * @return string
     */
    public function detectEnvironment(callable $callback): string
    {
        $args = $_SERVER['argv'] ?? null;

        return $this['env'] = (new EnvironmentDetector)->detect($callback, $args);
    }

    /**
     * Determine if the application signals are cached.
     *
     * @return bool
     * @throws ReflectionException
     */
    public function signalsAreCached(): bool
    {
        if ($this->isBound('signals.cached')) {
            return (bool) $this->make('signals.cached');
        }

        try {
            $this->registerInstance(
                'signals.cached', $this['files']->exists($this->getCachedSignalsPath())
            );

            return true;
        }
        catch (Exception $e)
        {
            return false;
        }

    }

    /**
     * Get the path to the signals cache file.
     *
     * @return string
     */
    public function getCachedSignalsPath(): string
    {
        return $this->normalizeCachePath('APP_SIGNALS_CACHE', 'cache/signals.php');
    }

    /**
     * Whether the framework's own config should be merged under the app's.
     *
     * @return bool
     */
    public function shouldMergeFrameworkConfiguration(): bool
    {
        return $this->merge_framework_configuration;
    }

    /**
     * Publish every config key, rather than only the ones the app overrides.
     *
     * @return $this
     */
    public function dontMergeFrameworkConfiguration(): static
    {
        $this->merge_framework_configuration = false;

        return $this;
    }

    /**
     * The app's root namespace, read off the psr-4 entry that points at the app directory.
     *
     * @return string
     * @throws RuntimeException
     */
    public function getNamespace(): string
    {
        if (! is_null($this->namespace)) {
            return $this->namespace;
        }

        $composer = json_decode(file_get_contents($this->basePath('composer.json')), true);

        foreach ((array) ($composer['autoload']['psr-4'] ?? []) as $namespace => $paths)
        {
            foreach ((array) $paths as $path)
            {
                if (realpath($this->path()) === realpath($this->basePath($path))) {
                    return $this->namespace = $namespace;
                }
            }
        }

        throw new RuntimeException('Unable to detect application namespace.');
    }
}