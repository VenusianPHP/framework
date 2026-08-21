<?php

namespace Voyager\System\Support\Providers;

use Voyager\System\Events\DiscoverEvents;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\MagicAliases\Event;
use Voyager\NutsAndBolts\LazyCollection;
use Voyager\NutsAndBolts\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [];

    /**
     * The subscribers to register.
     *
     * @var array
     */
    protected $subscribe = [];

    /**
     * The model observers to register.
     *
     * @var array<string, string|object|array<int, string|object>>
     */
    protected $observers = [];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * The configured event discovery paths.
     *
     * @var iterable<int, string>|null
     */
    protected static $eventDiscoveryPaths;

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function register(): void
    {
        $this->booting(function () {
            $events = $this->getEvents();

            foreach ($events as $event => $listeners) {
                foreach (array_unique($listeners, SORT_REGULAR) as $listener) {
                    Event::listen($event, $listener);
                }
            }

            foreach ($this->subscribe as $subscriber) {
                Event::subscribe($subscriber);
            }

            foreach ($this->observers as $model => $observers) {
                $model::observe($observers);
            }
        });

        $this->booted(function () {
        });
    }

    /**
     * Boot any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the events and handlers.
     *
     * @return array
     */
    public function listens(): array
    {
        return $this->listen;
    }

    /**
     * Get the discovered events and listeners for the application.
     *
     * @return array
     */
    public function getEvents(): array
    {
        if ($this->app->eventsAreCached()) {
            $cache = require $this->app->getCachedEventsPath();

            return $cache[get_class($this)] ?? [];
        } else {
            return array_merge_recursive(
                $this->discoveredEvents(),
                $this->listens()
            );
        }
    }

    /**
     * Get the discovered events for the application.
     *
     * @return array
     */
    protected function discoveredEvents(): array
    {
        return $this->shouldDiscoverEvents()
            ? $this->discoverEvents()
            : [];
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        return get_class($this) === __CLASS__ && static::$shouldDiscoverEvents === true;
    }

    /**
     * Discover the events and listeners for the application.
     *
     * @return array
     */
    public function discoverEvents(): array
    {
        return (new LazyCollection($this->discoverEventsWithin()))
            ->flatMap(function ($directory) {
                return glob($directory, GLOB_ONLYDIR);
            })
            ->reject(function ($directory) {
                return ! is_dir($directory);
            })
            ->pipe(fn ($directories) => DiscoverEvents::within(
                $directories->all(),
                $this->eventDiscoveryBasePath(),
            ));
    }

    /**
     * Get the listener directories that should be used to discover events.
     *
     * @return iterable<int, string>
     */
    protected function discoverEventsWithin()
    {
        return static::$eventDiscoveryPaths ?: [
            $this->app->path('Listeners'),
        ];
    }

    /**
     * Add the given event discovery paths to the application's event discovery paths.
     *
     * @param  string|iterable<int, string>  $paths
     * @return void
     */
    public static function addEventDiscoveryPaths(iterable|string $paths): void
    {
        static::$eventDiscoveryPaths = (new LazyCollection(static::$eventDiscoveryPaths))
            ->merge(is_string($paths) ? [$paths] : $paths)
            ->unique()
            ->values();
    }

    /**
     * Set the globally configured event discovery paths.
     *
     * @param  iterable<int, string>  $paths
     * @return void
     */
    public static function setEventDiscoveryPaths(iterable $paths): void
    {
        static::$eventDiscoveryPaths = $paths;
    }

    /**
     * Get the base path to be used during event discovery.
     *
     * @return string
     */
    protected function eventDiscoveryBasePath(): string
    {
        return base_path();
    }

    /**
     * Disable event discovery for the application.
     *
     * @return void
     */
    public static function disableEventDiscovery(): void
    {
        static::$shouldDiscoverEvents = false;
    }

}
