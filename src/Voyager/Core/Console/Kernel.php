<?php

namespace Voyager\Core\Console;

use ReflectionException;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Voyager\Console\ComputerConsoleInstance;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Console\Signals\CommandFinished;
use Voyager\Console\Signals\CommandStarting;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Voyager\Contracts\Console\Kernel as KernelContract;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Contracts\Signals\SignalDispatcher;
use Voyager\Core\Bootstrap\BootProviders;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Carbon;

class Kernel implements KernelContract
{
    /**
     * The Computer commands provided by the application.
     *
     * @var array
     */
    protected array $commands = [];

    /**
     * The event dispatcher implementation.
     *
     * @var SignalDispatcher
     */
    protected SignalDispatcher $signals;

    /**
     * The paths where Computer commands should be automatically discovered.
     *
     * @var array
     */
    protected array $command_paths = [];

    /**
     * When the currently handled command started.
     *
     * @var Carbon|null
     */
    protected ?Carbon $command_started_at = null;

    /**
     * Every registered command duration handler.
     *
     * @var array
     */
    protected array $command_lifecycle_duration_handlers = [];

    /**
     * The Computer application instance.
     *
     * @var ComputerConsoleInstance|null
     */
    protected ?ComputerConsoleInstance $computer = null;

    /**
     * The Symfony event dispatcher implementation.
     *
     * @var EventDispatcherInterface|null
     */
    protected ?EventDispatcherInterface $symfony_dispatcher = null;

    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected array $bootstrappers = [
        \Voyager\Core\Bootstrap\LoadEnvironmentVariables::class,
        \Voyager\Core\Bootstrap\LoadConfiguration::class,
        \Voyager\Core\Bootstrap\HandleExceptions::class,
        //\Voyager\Core\Bootstrap\RegisterMagicAliases::class,
        \Voyager\Core\Bootstrap\RegisterProviders::class,
        \Voyager\Core\Bootstrap\BootProviders::class,
    ];

    public function __construct(
        protected readonly FrameworkCore $app,
    ) {

        if (! defined('COMPUTER_BINARY')) {
            define('COMPUTER_BINARY', 'computer');
        }

        $app->booted(function () use($app) {
            if (! $app->runningUnitTests()) {
                $this->rerouteSymfonyCommandEvents();
            }
        });
    }

    /**
     * Set the Computer commands provided by the application.
     *
     * @param  array  $commands
     * @return $this
     */
    public function addCommands(array $commands): static
    {
        $this->commands = array_values(array_unique(array_merge($this->commands, $commands)));

        return $this;
    }

    /**
     * Set the paths that should have their Computer commands automatically discovered.
     *
     * @param  array  $paths
     * @return $this
     */
    public function addCommandPaths(array $paths): static
    {
        $this->command_paths = array_values(array_unique(array_merge($this->command_paths, $paths)));

        return $this;
    }

    /**
     * Re-route the Symfony command events to their Venusian counterparts.
     *
     * @internal
     *
     * @return $this
     */
    public function rerouteSymfonyCommandEvents(): static
    {
        if (is_null($this->symfony_dispatcher)) {
            $this->symfony_dispatcher = new EventDispatcher;


            $this->symfony_dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
                $this->signals->dispatch(
                    new CommandStarting($event->getCommand()?->getName() ?? '', $event->getInput(), $event->getOutput())
                );
            });

            $this->symfony_dispatcher->addListener(ConsoleEvents::TERMINATE, function (ConsoleTerminateEvent $event) {
                $this->signals->dispatch(
                    new CommandFinished($event->getCommand()?->getName() ?? '', $event->getInput(), $event->getOutput(), $event->getExitCode())
                );
            });
        }

        return $this;
    }

    public function bootstrap(): void
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }

        // Deferred providers register their commands on Computer::starting().
        // That has to happen before the console instance is built, or the
        // list only contains Symfony's own help and list.
        $this->app->loadDeferredProviders();
    }

    public function handle(InputInterface $input, ?OutputInterface $output = null): int
    {
        $this->command_started_at = Carbon::now();

        try {
            if (in_array($input->getFirstArgument(), ['env:encrypt', 'env:decrypt'], true)) {
                $this->bootstrapWithoutBootingProviders();
            }

            $this->bootstrap();

            return $this->getComputer()->run($input, $output);
        }
        catch (Throwable $e) {
            $this->reportException($e);

            $this->renderException($output, $e);

            return 1;
        }
    }

    /**
     * @throws ReflectionException
     */
    public function terminate(InputInterface $input, int $status): void
    {
        //$this->events->dispatch(new Terminating);

        $this->app->terminate();

        if ($this->command_started_at === null) {
            return;
        }

        $this->command_started_at->setTimezone($this->app['config']->get('app.timezone') ?? 'UTC');

        foreach ($this->command_lifecycle_duration_handlers as ['threshold' => $threshold, 'handler' => $handler]) {
            $end ??= Carbon::now();

            if ($this->command_started_at->diffInMilliseconds($end) > $threshold) {
                $handler($this->command_started_at, $input, $status);
            }
        }

        $this->command_started_at = null;
    }

    /**
     * Bootstrap the application without booting service providers.
     *
     * @return void
     */
    public function bootstrapWithoutBootingProviders(): void
    {
        $this->app->bootstrapWith(
            new Collection($this->bootstrappers())
                ->reject(fn ($bootstrapper) => $bootstrapper === BootProviders::class)
                ->all()
        );
    }

    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers(): array
    {
        return $this->bootstrappers;
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param  Throwable  $e
     * @return void
     */
    protected function reportException(Throwable $e): void
    {
        $this->app[ExceptionHandler::class]->report($e);
    }

    /**
     * Render the given exception.
     *
     * @param OutputInterface $output
     * @param  Throwable  $e
     * @return void
     */
    protected function renderException(OutputInterface $output, Throwable $e): void
    {
        $this->app[ExceptionHandler::class]->renderForConsole($output, $e);
    }

    /**
     * Get the Computer application instance.
     *
     * @return ComputerConsoleInstance
     * @throws \ReflectionException
     */
    protected function getComputer(): ComputerConsoleInstance
    {

        if (is_null($this->computer)) {
            $this->computer = new ComputerConsoleInstance($this->app, /*$this->events,*/ $this->app->version())
                ->resolveCommands($this->commands)
                ->setContainerCommandLoader();

            /*if ($this->symfony_dispatcher instanceof EventDispatcher) {
                $this->computer->setDispatcher($this->symfony_dispatcher);
                $this->computer->setSignalsToDispatchEvent();
            }*/
        }

        return $this->computer;
    }
}