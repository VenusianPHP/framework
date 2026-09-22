<?php

namespace Voyager\Core\Concerns;

use ReflectionException;
use Voyager\Filesystem\Filesystem;
use Voyager\Log\ContextServiceProvider;
use Voyager\Log\LogServiceProvider;
use Voyager\Signals\SignalServiceProvider;
use Voyager\Vessel\ControlPanel;
use Voyager\Core\PackageManifest;
use Psr\Container\ContainerInterface;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\DataObjects\Env;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Contracts\Vessel\TheServiceContainer;

trait InstanceBootstrapping
{
    /**
     * Every registered service provider.
     *
     * @var array<string, ServiceProvider>
     */
    protected array $service_providers = [];

    /**
     * The app's root namespace, resolved once off composer.json.
     *
     * @var string|null
     */
    protected ?string $namespace = null;

    /**
     * Whether the framework's own config is merged under the app's.
     *
     * @var bool
     */
    protected bool $merge_framework_configuration = true;

    /**
     * The names of the loaded service providers.
     *
     * @var array
     */
    protected array $loaded_providers = [];

    /**
     * The array of booting callbacks.
     *
     * @var callable[]
     */
    protected array $booting_callbacks = [];

    /**
     * The array of booted callbacks.
     *
     * @var callable[]
     */
    protected array $booted_callbacks = [];

    /**
     * The deferred services and their providers.
     *
     * @var array
     */
    protected array $deferred_services = [];

    /**
     * The array of terminating callbacks.
     *
     * @var callable[]
     */
    protected array $terminating_callbacks = [];

    /**
     * The array of registered callbacks.
     *
     * @var callable[]
     */
    protected array $registered_callbacks = [];

    /**
     * The prefixes of absolute cache paths for use during normalization.
     *
     * @var string[]
     */
    protected array $absolute_cache_path_prefixes = ['/', '\\'];

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
     * Resolve a service provider instance from the class name.
     *
     * @param string $provider
     * @return ServiceProvider
     */
    public function resolveProvider(string $provider): ServiceProvider
    {
        return new $provider($this);
    }

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param string|ServiceProvider $provider
     * @return ServiceProvider|null
     */
    public function getProvider(string|ServiceProvider $provider): ?ServiceProvider
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return $this->service_providers[$name] ?? null;
    }

    /**
     * Every registered provider that is an instance of the given one. Unlike getProvider(),
     * a subclass counts: callers ask for a base provider and want the app's own.
     *
     * @param string|ServiceProvider $provider
     * @return array<class-string, ServiceProvider>
     */
    public function getProviders(string|ServiceProvider $provider): array
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return array_filter($this->service_providers, fn (ServiceProvider $p) => $p instanceof $name);
    }

    /**
     * Flush the container of all bindings and resolved instances.
     *
     * @return void
     */
    public function flush(): void
    {
        parent::flush();

        $this->build_stack = [];
        $this->loaded_providers = [];
        $this->booted_callbacks = [];
        $this->booting_callbacks = [];
        $this->deferred_services = [];
        $this->rebound_callbacks = [];
        $this->service_providers = [];
        $this->resolving_callbacks = [];
        $this->terminating_callbacks = [];
        $this->before_resolving_callbacks = [];
        $this->after_resolving_callbacks = [];
        $this->global_before_resolving_callbacks = [];
        $this->global_resolving_callbacks = [];
        $this->global_after_resolving_callbacks = [];
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

        return Str::startsWith($env, $this->absolute_cache_path_prefixes)
            ? $env
            : $this->basePath($env);
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

        $this->service_providers[$class] = $provider;

        $this->loaded_providers[$class] = true;
    }

    /**
     * Boot the given service provider.
     *
     * @param ServiceProvider $provider
     * @return void
     * @throws ReflectionException
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
     * Register the basic bindings into the container.
     *
     * @return void
     * @throws ReflectionException
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->registerInstance('app', $this);

        $this->registerInstance(ControlPanel::class, $this);


        $this->registerSingleton(PackageManifest::class, fn () => new PackageManifest(
            new Filesystem, $this->basePath(), $this->getCachedPackagesPath()
        ));
    }

    /**
     * Register every base service provider.
     *
     * @return void
     * @throws ReflectionException
     */
    protected function registerBaseServiceProviders(): void
    {
        $this->register(new SignalServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new ContextServiceProvider($this));
    }

    protected function registerCoreContainerAliases(): void
    {
        $core_aliases = [
            'app' => [self::class, TheServiceContainer::class, FrameworkCore::class, ContainerInterface::class],
            'broadcast' => [\Voyager\Broadcasting\BroadcastManager::class, \Voyager\Contracts\Broadcasting\Factory::class],
            'broadcast.connection' => [\Voyager\Contracts\Broadcasting\Broadcaster::class],
            'cache' => [\Voyager\Cache\CacheManager::class, \Voyager\Contracts\Cache\Factory::class],
            'cache.store' => [\Voyager\Cache\Repository::class, \Voyager\Contracts\Cache\Repository::class, \Psr\SimpleCache\CacheInterface::class],
            'config' => [\Voyager\Config\Repository::class, \Voyager\Contracts\Config\Repository::class],
            'event-loop' => [\Voyager\IOPools\EventLoop::class, \Voyager\Contracts\IOPools\Loop::class],
            'hash' => [\Voyager\Hashing\HashManager::class, \Voyager\Contracts\Hashing\Hasher::class],
            'hash.driver' => [\Voyager\Contracts\Hashing\Hasher::class],
            'http' => [\Voyager\Http\Client\Factory::class],
            'http.async' => [\Voyager\Http\Async\HttpAsyncManager::class],
            'filesystem' => [\Voyager\Filesystem\FilesystemManager::class, \Voyager\Contracts\Filesystem\Factory::class],
            'filesystem.disk' => [\Voyager\Contracts\Filesystem\Filesystem::class],
            'filesystem.cloud' => [\Voyager\Contracts\Filesystem\Cloud::class],
            'log' => [\Voyager\Log\LogManager::class, \Psr\Log\LoggerInterface::class],
            'queue' => [\Voyager\Queue\QueueManager::class, \Voyager\Contracts\Queue\Factory::class, \Voyager\Contracts\Queue\Monitor::class],
            'queue.connection' => [\Voyager\Contracts\Queue\Queue::class],
            'queue.failer' => [\Voyager\Queue\Failed\FailedJobProviderInterface::class],
            'redis' => [\Voyager\Redis\RedisManager::class, \Voyager\Contracts\Redis\Factory::class],
            'redis.connection' => [\Voyager\Redis\Connections\Connection::class, \Voyager\Contracts\Redis\Connection::class],
            'signals' => [\Voyager\Signals\SignalDispatcher::class, \Voyager\Contracts\Signals\SignalDispatcher::class],
            'work-targets' => [\Voyager\IOPools\WorkTargetManager::class],
        ];

        foreach($core_aliases as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
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



}