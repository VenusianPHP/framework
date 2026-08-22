<?php

namespace Voyager\System\Configuration;

use Closure;
use Voyager\Console\Application as Computer;
use Voyager\Console\Scheduling\Schedule;
use Voyager\Contracts\Console\Kernel as ConsoleKernel;
use Voyager\System\Application;
use Voyager\System\Bootstrap\RegisterProviders;
use Voyager\System\Events\DiagnosingHealth;
use Voyager\System\Support\Providers\EventServiceProvider as AppEventServiceProvider;
use Voyager\NutsAndBolts\Collection;
use Voyager\MagicAliases\Broadcast;
use Voyager\NutsAndBolts\MagicAliases\Event;
use Voyager\MagicAliases\Route;
use Voyager\MagicAliases\View;

class ApplicationBuilder
{
    /**
     * The service provider that are marked for registration.
     *
     * @var array
     */
    protected array $pendingProviders = [];

    /**
     * Any additional routing callbacks that should be invoked while registering routes.
     *
     * @var array
     */
    protected array $additionalRoutingCallbacks = [];

    /**
     * Create a new application builder instance.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Register the standard kernel classes for the application.
     *
     * @return $this
     */
    public function withKernels(): static
    {
        $this->app->singleton(
            \Voyager\Contracts\Console\Kernel::class,
            \Voyager\System\Console\Kernel::class,
        );

        $this->app->singleton(
            \Voyager\Contracts\Sketches\Kernel::class,
            \Voyager\System\Sketches\Kernel::class,
        );

        return $this;
    }

    /**
     * Register additional service providers.
     *
     * @param  array  $providers
     * @param  bool  $withBootstrapProviders
     * @return $this
     */
    public function withProviders(array $providers = [], bool $withBootstrapProviders = true): static
    {
        RegisterProviders::merge(
            $providers,
            $withBootstrapProviders
                ? $this->app->getBootstrapProvidersPath()
                : null
        );

        return $this;
    }

    /**
     * Register the core event service provider for the application.
     *
     * @param  iterable<int, string>|bool  $discover
     * @return $this
     */
    public function withEvents(iterable|bool $discover = true): static
    {
        if (is_iterable($discover)) {
            AppEventServiceProvider::setEventDiscoveryPaths($discover);
        }

        if ($discover === false) {
            AppEventServiceProvider::disableEventDiscovery();
        }

        if (! isset($this->pendingProviders[AppEventServiceProvider::class])) {
            $this->app->booting(function () {
                $this->app->register(AppEventServiceProvider::class);
            });
        }

        $this->pendingProviders[AppEventServiceProvider::class] = true;

        return $this;
    }

    /**
     * Register the broadcasting services for the application.
     *
     * @param  string  $channels
     * @param  array  $attributes
     * @return $this
     */
    public function withBroadcasting(string $channels, array $attributes = []): static
    {
        $this->app->booted(function () use ($channels, $attributes) {
            Broadcast::routes(! empty($attributes) ? $attributes : null);

            if (file_exists($channels)) {
                require $channels;
            }
        });

        return $this;
    }

    /**
     * Register additional Computer commands with the application.
     *
     * @param  array  $commands
     * @return $this
     */
    public function withCommands(array $commands = []): static
    {
        if (empty($commands)) {
            $commands = [$this->app->path('Console/Commands')];
        }

        $this->app->afterResolving(ConsoleKernel::class, function ($kernel) use ($commands) {
            [$commands, $paths] = new Collection($commands)->partition(fn ($command) => class_exists($command));
            [$routes, $paths] = $paths->partition(fn ($path) => is_file($path));

            $this->app->booted(static function () use ($kernel, $commands, $paths, $routes) {
                $kernel->addCommands($commands->all());
                $kernel->addCommandPaths($paths->all());
                $kernel->addCommandRoutePaths($routes->all());
            });
        });

        return $this;
    }

    /**
     * Register additional Computer route paths.
     *
     * @param  array  $paths
     * @return $this
     */
    protected function withCommandRouting(array $paths): static
    {
        $this->app->afterResolving(ConsoleKernel::class, function ($kernel) use ($paths) {
            $this->app->booted(fn () => $kernel->addCommandRoutePaths($paths));
        });

        return $this;
    }

    /**
     * Register the scheduled tasks for the application.
     *
     * @param  callable(\Voyager\Console\Scheduling\Schedule $schedule): void  $callback
     * @return $this
     */
    public function withSchedule(callable $callback): static
    {
        Computer::starting(fn () => $callback($this->app->make(Schedule::class)));

        return $this;
    }

    /**
     * Register and configure the application's exception handler.
     *
     * @param  callable(\Voyager\System\Configuration\Exceptions)|null  $using
     * @return $this
     */
    public function withExceptions(?callable $using = null): static
    {
        $this->app->singleton(
            \Voyager\Contracts\Debug\ExceptionHandler::class,
            \Voyager\System\Exceptions\Handler::class
        );

        if ($using !== null) {
            $this->app->afterResolving(
                \Voyager\System\Exceptions\Handler::class,
                fn ($handler) => $using(new Exceptions($handler)),
            );
        }

        return $this;
    }

    /**
     * Register an array of container bindings to be bound when the application is booting.
     *
     * @param  array  $bindings
     * @return $this
     */
    public function withBindings(array $bindings): static
    {
        return $this->registered(function ($app) use ($bindings) {
            foreach ($bindings as $abstract => $concrete) {
                $app->bind($abstract, $concrete);
            }
        });
    }

    /**
     * Register an array of singleton container bindings to be bound when the application is booting.
     *
     * @param  array  $singletons
     * @return $this
     */
    public function withSingletons(array $singletons): static
    {
        return $this->registered(function ($app) use ($singletons) {
            foreach ($singletons as $abstract => $concrete) {
                if (is_string($abstract)) {
                    $app->singleton($abstract, $concrete);
                } else {
                    $app->singleton($concrete);
                }
            }
        });
    }

    /**
     * Register an array of scoped singleton container bindings to be bound when the application is booting.
     *
     * @param  array  $scopedSingletons
     * @return $this
     */
    public function withScopedSingletons(array $scopedSingletons): static
    {
        return $this->registered(function ($app) use ($scopedSingletons) {
            foreach ($scopedSingletons as $abstract => $concrete) {
                if (is_string($abstract)) {
                    $app->scoped($abstract, $concrete);
                } else {
                    $app->scoped($concrete);
                }
            }
        });
    }

    /**
     * Register a callback to be invoked when the application's service providers are registered.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function registered(callable $callback): static
    {
        $this->app->registered($callback);

        return $this;
    }

    /**
     * Register a callback to be invoked when the application is "booting".
     *
     * @param  callable  $callback
     * @return $this
     */
    public function booting(callable $callback): static
    {
        $this->app->booting($callback);

        return $this;
    }

    /**
     * Register a callback to be invoked when the application is "booted".
     *
     * @param  callable  $callback
     * @return $this
     */
    public function booted(callable $callback): static
    {
        $this->app->booted($callback);

        return $this;
    }

    /**
     * Get the application instance.
     *
     * @return \Voyager\System\Application
     */
    public function create(): Application
    {
        return $this->app;
    }
}
