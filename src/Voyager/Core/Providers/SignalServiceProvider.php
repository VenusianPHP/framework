<?php

namespace Voyager\Core\Providers;

use ReflectionException;
use Voyager\Contracts\Signals\SignalDispatcher;
use Voyager\Core\Signals\DiscoverSignals;
use Voyager\NutsAndBolts\Contracts\Enumerable;
use Voyager\NutsAndBolts\LazyCollection;
use Voyager\NutsAndBolts\ServiceProvider;

class SignalServiceProvider extends ServiceProvider
{
    /**
     * The signal handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected array $listen = [];

    /**
     * The subscribers to register.
     *
     * @var array
     */
    protected array $subscribe = [];

    /**
     * The model observers to register.
     *
     * @var array<string, string|object|array<int, string|object>>
     */
    protected array $observers = [];

    /**
     * Indicates if signals should be discovered.
     *
     * @var bool
     */
    protected static bool $should_discover_signals = true;

    /**
     * The configured signal discovery paths.
     *
     * @var iterable<int, string>|null
     */
    protected static array|Enumerable $signal_discovery_paths = [];

    /**
     * Register the application's signal listeners.
     *
     * @return void
     */
    public function register(): void
    {
        $this->booting(function () {
            /** @var SignalDispatcher $dispatcher */
            $dispatcher = $this->app->get('signals');
            $signals = $this->getEvents();

            foreach ($signals as $signal => $listeners) {
                foreach (array_unique($listeners, SORT_REGULAR) as $listener) {
                    $dispatcher->listen($signal, $listener);
                }
            }

            foreach ($this->subscribe as $subscriber) {
                $dispatcher->subscribe($subscriber);
            }

            foreach ($this->observers as $model => $observers) {
                $model::observe($observers);
            }
        });

        $this->booted(function () {});
    }

    /**
     * Boot any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Get the signals and handlers.
     *
     * @return array
     */
    public function listens(): array
    {
        return $this->listen;
    }

    /**
     * Get the discovered signals and listeners for the application.
     *
     * @return array
     * @throws ReflectionException
     */
    public function getEvents(): array
    {
        if ($this->app->signalsAreCached()) {
            $cache = require $this->app->getCachedSignalsPath();

            return $cache[get_class($this)] ?? [];
        } else {
            return array_merge_recursive(
                $this->discoveredEvents(),
                $this->listens()
            );
        }
    }

    /**
     * Get the discovered signals for the application.
     *
     * @return array
     * @throws ReflectionException
     */
    protected function discoveredEvents(): array
    {
        return $this->shouldDiscoverEvents()
            ? $this->discoverSignals()
            : [];
    }

    /**
     * Determine if signals and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        return get_class($this) === __CLASS__ && static::$should_discover_signals === true;
    }

    /**
     * Discover the signals and listeners for the application.
     *
     * @return array
     * @throws ReflectionException
     */
    public function discoverSignals(): array
    {
        return new LazyCollection($this->discoverSignalsWithin())
            ->flatMap(function ($directory) {
                return glob($directory, GLOB_ONLYDIR);
            })
            ->reject(function ($directory) {
                return ! is_dir($directory);
            })
            ->pipe(fn ($directories) => DiscoverSignals::within(
                $directories->all(),
                $this->signalDiscoveryBasePath(),
            ));
    }

    /**
     * Get the listener directories that should be used to discover signals.
     *
     * @return iterable<int, string>
     */
    protected function discoverSignalsWithin(): array
    {
        return static::$signal_discovery_paths ?: [
            $this->app->path('Listeners'),
        ];
    }

    /**
     * Add the given signal discovery paths to the application's signal discovery paths.
     *
     * @param iterable|string $paths
     * @return void
     */
    public static function addEventDiscoveryPaths(iterable|string $paths): void
    {
        static::$signal_discovery_paths = new LazyCollection(static::$signal_discovery_paths)
            ->merge(is_string($paths) ? [$paths] : $paths)
            ->unique()
            ->values();
    }

    /**
     * Set the globally configured signal discovery paths.
     *
     * @param  iterable<int, string>  $paths
     * @return void
     */
    public static function setSignalDiscoveryPaths(iterable $paths): void
    {
        static::$signal_discovery_paths = $paths;
    }

    /**
     * Get the base path to be used during signal discovery.
     *
     * @return string
     * @throws ReflectionException
     */
    protected function signalDiscoveryBasePath(): string
    {
        return base_path();
    }

    /**
     * Disable signal discovery for the application.
     *
     * @return void
     */
    public static function disableSignalDiscovery(): void
    {
        static::$should_discover_signals = false;
    }
}