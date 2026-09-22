<?php

namespace Voyager\Core\Bootstrap;

use ReflectionException;
use Voyager\Core\RenderedInstance;
use Voyager\Contracts\Console\Kernel as ConsoleKernel;
use Voyager\Contracts\Sketches\Kernel as SketchKernel;
use Voyager\NutsAndBolts\Collection;
use Voyager\Core\Providers\SignalServiceProvider as AppSignalServiceProvider;

class ConfigFactory
{
    public RenderedInstance $instance;

    /**
     * The service provider that are marked for registration.
     *
     * @var array
     */
    protected array $pending_providers = [];

    public function __construct(
        ?RenderedInstance $rendered_instance = null
    ) {
        $this->instance = $rendered_instance ?: new RenderedInstance();
    }

    /**
     * Register the standard kernel classes for the application.
     *
     * @return $this
     * @throws ReflectionException
     */
    public function withKernels(): static
    {
        $this->instance->registerSingleton(
            \Voyager\Contracts\Console\Kernel::class,
            \Voyager\Core\Console\Kernel::class,
        );

        $this->instance->registerSingleton(
            \Voyager\Contracts\Sketches\Kernel::class,
            \Voyager\Core\Sketches\Kernel::class,
        );

        return $this;
    }

    public function withSignals(iterable|bool $discover = true): static
    {
        if (is_iterable($discover)) {
            AppSignalServiceProvider::setSignalDiscoveryPaths($discover);
        }

        if ($discover === false) {
            AppSignalServiceProvider::disableSignalDiscovery();
        }

        if (! isset($this->pending_providers[AppSignalServiceProvider::class])) {
            $this->instance->booting(function () {
                $this->instance->register(AppSignalServiceProvider::class);
            });
        }

        $this->pending_providers[AppSignalServiceProvider::class] = true;

        return $this;
    }

    public function withCommands(array $commands = []): static
    {
        if (empty($commands)) {
            $commands = [$this->instance->path('Console/Commands')];
        }

        $this->instance->afterResolving(ConsoleKernel::class, function (ConsoleKernel $kernel) use ($commands) {
            [$commands, $paths] = new Collection($commands)->partition(fn ($command) => class_exists($command));
            [$routes, $paths] = $paths->partition(fn ($path) => is_file($path));

            $this->instance->booted(static function () use ($kernel, $commands, $paths) {
                $kernel->addCommands($commands->all());
                $kernel->addCommandPaths($paths->all());
            });
        });

        return $this;
    }

    public function withProviders(array $providers = [], bool $with_bootstrap_providers = true): static
    {
        RegisterProviders::merge(
            $providers,
            $with_bootstrap_providers
                ? $this->instance->getBootstrapProvidersPath()
                : null
        );

        return $this;
    }

    public function withSketches(array $sketches = []): static
    {
        if (empty($sketches)) {
            $sketches = [$this->instance->path('Console/Sketches')];
        }

        $this->instance->afterResolving(SketchKernel::class, function (SketchKernel $kernel) use ($sketches) {
            [$commands, $paths] = new Collection($sketches)->partition(fn ($command) => class_exists($command));
            [$routes, $paths] = $paths->partition(fn ($path) => is_file($path));

            $this->instance->booted(static function () use ($kernel, $commands, $paths) {
                $kernel->addSketches($commands->all());
                $kernel->addSketchPaths($paths->all());
            });
        });

        return $this;
    }

    /**
     * Register and configure the application's exception handler.
     *
     * @param callable|null $using
     * @return $this
     * @throws ReflectionException
     */
    public function withExceptions(?callable $using = null): static
    {
        $this->instance->registerSingleton(
            \Voyager\Contracts\Debug\ExceptionHandler::class,
            \Voyager\Core\Exceptions\Handler::class
        );

        if ($using !== null) {
            $this->instance->afterResolving(
                \Voyager\Core\Exceptions\Handler::class,
                fn ($handler) => $using(new Exceptions($handler)),
            );
        }

        return $this;
    }

    public function create(): RenderedInstance
    {
        return $this->instance;
    }
}