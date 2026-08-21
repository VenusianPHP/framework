<?php

namespace Voyager\System\Console;

use Carbon\CarbonInterval;
use Closure;
use DateTimeInterface;
use Voyager\Console\Application as Computer;
use Voyager\Console\Command;
use Voyager\Console\Events\CommandFinished;
use Voyager\Console\Events\CommandStarting;
use Voyager\Console\Scheduling\Schedule;
use Voyager\Contracts\Console\Kernel as KernelContract;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Contracts\System\Application;
use Voyager\System\Events\Terminating;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Env;
use Voyager\NutsAndBolts\Concerns\InteractsWithTime;
use Voyager\NutsAndBolts\DataObjects\Str;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Finder\Finder;
use Throwable;
use WeakMap;

class Kernel implements KernelContract
{
    use InteractsWithTime;

    /**
     * The application implementation.
     *
     * @var \Voyager\Contracts\System\Application
     */
    protected \Voyager\Contracts\System\Application $app;

    /**
     * The event dispatcher implementation.
     *
     * @var \Voyager\Contracts\Events\Dispatcher
     */
    protected \Voyager\Contracts\Events\Dispatcher $events;

    /**
     * The Symfony event dispatcher implementation.
     *
     * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|null
     */
    protected ?\Symfony\Contracts\EventDispatcher\EventDispatcherInterface $symfonyDispatcher = null;

    /**
     * The Computer application instance.
     *
     * @var \Voyager\Console\Application|null
     */
    protected ?\Voyager\Console\Application $computer = null;

    /**
     * The Computer commands provided by the application.
     *
     * @var array
     */
    protected array $commands = [];

    /**
     * The paths where Computer commands should be automatically discovered.
     *
     * @var array
     */
    protected array $commandPaths = [];

    /**
     * The paths where Computer "routes" should be automatically discovered.
     *
     * @var array
     */
    protected array $commandRoutePaths = [];

    /**
     * Indicates if the Closure commands have been loaded.
     *
     * @var bool
     */
    protected bool $commandsLoaded = false;

    /**
     * The commands paths that have been "loaded".
     *
     * @var array
     */
    protected array $loadedPaths = [];

    /**
     * Every registered command duration handler.
     *
     * @var array
     */
    protected array $commandLifecycleDurationHandlers = [];

    /**
     * When the currently handled command started.
     *
     * @var \Voyager\NutsAndBolts\DataObjects\Carbon|null
     */
    protected ?\Voyager\NutsAndBolts\DataObjects\Carbon $commandStartedAt = null;

    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [
        \Voyager\System\Bootstrap\LoadEnvironmentVariables::class,
        \Voyager\System\Bootstrap\LoadConfiguration::class,
        \Voyager\System\Bootstrap\HandleExceptions::class,
        \Voyager\System\Bootstrap\RegisterMagicAliases::class,
        \Voyager\System\Bootstrap\RegisterProviders::class,
        \Voyager\System\Bootstrap\BootProviders::class,
    ];

    /**
     * Create a new console kernel instance.
     *
     * @param  \Voyager\Contracts\System\Application  $app
     * @param  \Voyager\Contracts\Events\Dispatcher  $events
     */
    public function __construct(Application $app, Dispatcher $events)
    {
        if (! defined('COMPUTER_BINARY')) {
            define('COMPUTER_BINARY', 'computer');
        }

        $this->app = $app;
        $this->events = $events;

        $this->app->booted(function () {
            if (! $this->app->runningUnitTests()) {
                $this->rerouteSymfonyCommandEvents();
            }
        });
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
        if (is_null($this->symfonyDispatcher)) {
            $this->symfonyDispatcher = new EventDispatcher;

            $this->symfonyDispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
                $this->events->dispatch(
                    new CommandStarting($event->getCommand()?->getName() ?? '', $event->getInput(), $event->getOutput())
                );
            });

            $this->symfonyDispatcher->addListener(ConsoleEvents::TERMINATE, function (ConsoleTerminateEvent $event) {
                $this->events->dispatch(
                    new CommandFinished($event->getCommand()?->getName() ?? '', $event->getInput(), $event->getOutput(), $event->getExitCode())
                );
            });
        }

        return $this;
    }

    /**
     * Run the console application.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $output
     * @return int
     */
    public function handle(\Symfony\Component\Console\Input\InputInterface $input, ?\Symfony\Component\Console\Output\OutputInterface $output = null): int
    {
        $this->commandStartedAt = Carbon::now();

        try {
            if (in_array($input->getFirstArgument(), ['env:encrypt', 'env:decrypt'], true)) {
                $this->bootstrapWithoutBootingProviders();
            }

            $this->bootstrap();

            return $this->getComputer()->run($input, $output);
        } catch (Throwable $e) {
            $this->reportException($e);

            $this->renderException($output, $e);

            return 1;
        }
    }

    /**
     * Terminate the application.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  int  $status
     * @return void
     */
    public function terminate(\Symfony\Component\Console\Input\InputInterface $input, int $status): void
    {
        $this->events->dispatch(new Terminating);

        $this->app->terminate();

        if ($this->commandStartedAt === null) {
            return;
        }

        $this->commandStartedAt->setTimezone($this->app['config']->get('app.timezone') ?? 'UTC');

        foreach ($this->commandLifecycleDurationHandlers as ['threshold' => $threshold, 'handler' => $handler]) {
            $end ??= Carbon::now();

            if ($this->commandStartedAt->diffInMilliseconds($end) > $threshold) {
                $handler($this->commandStartedAt, $input, $status);
            }
        }

        $this->commandStartedAt = null;
    }

    /**
     * Register a callback to be invoked when the command lifecycle duration exceeds a given amount of time.
     *
     * @param  \DateTimeInterface|\Carbon\CarbonInterval|float|int  $threshold
     * @param  callable  $handler
     * @return void
     */
    public function whenCommandLifecycleIsLongerThan(\DateTimeInterface|\Carbon\CarbonInterval|float|int $threshold, callable $handler): void
    {
        $threshold = $threshold instanceof DateTimeInterface
            ? $this->secondsUntil($threshold) * 1000
            : $threshold;

        $threshold = $threshold instanceof CarbonInterval
            ? $threshold->totalMilliseconds
            : $threshold;

        $this->commandLifecycleDurationHandlers[] = [
            'threshold' => $threshold,
            'handler' => $handler,
        ];
    }

    /**
     * When the command being handled started.
     *
     * @return \Voyager\NutsAndBolts\DataObjects\Carbon|null
     */
    public function commandStartedAt(): ?\Voyager\NutsAndBolts\DataObjects\Carbon
    {
        return $this->commandStartedAt;
    }

    /**
     * Define the application's command schedule.
     *
     * @param  \Voyager\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        //
    }

    /**
     * Resolve a console schedule instance.
     *
     * @return \Voyager\Console\Scheduling\Schedule
     */
    public function resolveConsoleSchedule(): \Voyager\Console\Scheduling\Schedule
    {
        return tap(new Schedule($this->scheduleTimezone()), function ($schedule) {
            $this->schedule($schedule->useCache($this->scheduleCache()));
        });
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     *
     * @return \DateTimeZone|string|null
     */
    protected function scheduleTimezone(): \DateTimeZone|string|null
    {
        $config = $this->app['config'];

        return $config->get('app.schedule_timezone', $config->get('app.timezone'));
    }

    /**
     * Get the name of the cache store that should manage scheduling mutexes.
     *
     * @return string|null
     */
    protected function scheduleCache(): ?string
    {
        return $this->app['config']->get('cache.schedule_store', Env::get('SCHEDULE_CACHE_DRIVER', function () {
            return Env::get('SCHEDULE_CACHE_STORE');
        }));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        //
    }

    /**
     * Register a Closure based command with the application.
     *
     * @param  string  $signature
     * @param  \Closure  $callback
     * @return \Voyager\System\Console\ClosureCommand
     */
    public function command(string $signature, Closure $callback): \Voyager\System\Console\ClosureCommand
    {
        $command = new ClosureCommand($signature, $callback);

        Computer::starting(function ($computer) use ($command) {
            $computer->add($command);
        });

        return $command;
    }

    /**
     * Register every command in the given directory.
     *
     * @param  array|string  $paths
     * @return void
     */
    protected function load(array|string $paths): void
    {
        $paths = array_unique(Arr::wrap($paths));

        $paths = array_filter($paths, function ($path) {
            return is_dir($path);
        });

        if (empty($paths)) {
            return;
        }

        $this->loadedPaths = array_values(
            array_unique(array_merge($this->loadedPaths, $paths))
        );

        $namespace = $this->app->getNamespace();

        $possibleCommands = new WeakMap;

        $filterCommands = function (SplFileInfo $file) use ($namespace, &$possibleCommands) {
            $commandClassName = $this->commandClassFromFile($file, $namespace);

            $possibleCommands[$file] = $commandClassName;

            $command = rescue(fn () => new ReflectionClass($commandClassName), null, false);

            return $command instanceof ReflectionClass
                && $command->isSubClassOf(Command::class)
                && ! $command->isAbstract();
        };

        foreach ($this->findCommands($paths)->filter($filterCommands) as $file) {
            Computer::starting(function ($computer) use ($file, $possibleCommands) {
                $computer->resolve($possibleCommands[$file]);
            });
        }
    }

    /**
     * Get the Finder instance for discovering command files.
     *
     * @param  array  $paths
     * @return \Symfony\Component\Finder\Finder
     */
    protected function findCommands(array $paths): \Symfony\Component\Finder\Finder
    {
        return Finder::create()->in($paths)->name('*.php')->files();
    }

    /**
     * Extract the command class name from the given file path.
     *
     * @param  \SplFileInfo  $file
     * @param  string  $namespace
     * @return string
     */
    protected function commandClassFromFile(SplFileInfo $file, string $namespace): string
    {
        return $namespace.str_replace(
            ['/', '.php'],
            ['\\', ''],
            Str::after($file->getRealPath(), realpath(app_path()).DIRECTORY_SEPARATOR)
        );
    }

    /**
     * Register the given command with the console application.
     *
     * @param  \Symfony\Component\Console\Command\Command  $command
     * @return void
     */
    public function registerCommand(\Symfony\Component\Console\Command\Command $command): void
    {
        $this->getComputer()->add($command);
    }

    /**
     * Run an Computer console command by name.
     *
     * @param  \Symfony\Component\Console\Command\Command|string  $command
     * @param  array  $parameters
     * @param  \Symfony\Component\Console\Output\OutputInterface|null  $outputBuffer
     * @return int
     *
     * @throws \Symfony\Component\Console\Exception\CommandNotFoundException
     */
    public function call(\Symfony\Component\Console\Command\Command|string $command, array $parameters = [], ?\Symfony\Component\Console\Output\OutputInterface $outputBuffer = null): int
    {
        if (in_array($command, ['env:encrypt', 'env:decrypt'], true)) {
            $this->bootstrapWithoutBootingProviders();
        }

        $this->bootstrap();

        return $this->getComputer()->call($command, $parameters, $outputBuffer);
    }

    /**
     * Queue the given console command.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return \Voyager\System\Bus\PendingDispatch
     */
    public function queue(string $command, array $parameters = []): \Voyager\System\Bus\PendingDispatch
    {
        return QueuedCommand::dispatch(func_get_args());
    }

    /**
     * Get every command registered with the console.
     *
     * @return array
     */
    public function all(): array
    {
        $this->bootstrap();

        return $this->getComputer()->all();
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output(): string
    {
        $this->bootstrap();

        return $this->getComputer()->output();
    }

    /**
     * Bootstrap the application for computer commands.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }

        $this->app->loadDeferredProviders();

        if (! $this->commandsLoaded) {
            $this->commands();

            if ($this->shouldDiscoverCommands()) {
                $this->discoverCommands();
            }

            $this->commandsLoaded = true;
        }
    }

    /**
     * Discover the commands that should be automatically loaded.
     *
     * @return void
     */
    protected function discoverCommands(): void
    {
        foreach ($this->commandPaths as $path) {
            $this->load($path);
        }

        foreach ($this->commandRoutePaths as $path) {
            if (file_exists($path)) {
                require $path;
            }
        }
    }

    /**
     * Bootstrap the application without booting service providers.
     *
     * @return void
     */
    public function bootstrapWithoutBootingProviders(): void
    {
        $this->app->bootstrapWith(
            (new Collection($this->bootstrappers()))
                ->reject(fn ($bootstrapper) => $bootstrapper === \Voyager\System\Bootstrap\BootProviders::class)
                ->all()
        );
    }

    /**
     * Determine if the kernel should discover commands.
     *
     * @return bool
     */
    protected function shouldDiscoverCommands(): bool
    {
        return get_class($this) === __CLASS__;
    }

    /**
     * Get the Computer application instance.
     *
     * @return \Voyager\Console\Application
     */
    protected function getComputer(): \Voyager\Console\Application
    {
        if (is_null($this->computer)) {
            $this->computer = (new Computer($this->app, $this->events, $this->app->version()))
                ->resolveCommands($this->commands)
                ->setContainerCommandLoader();

            if ($this->symfonyDispatcher instanceof EventDispatcher) {
                $this->computer->setDispatcher($this->symfonyDispatcher);
                $this->computer->setSignalsToDispatchEvent();
            }
        }

        return $this->computer;
    }

    /**
     * Set the Computer application instance.
     *
     * @param  \Voyager\Console\Application|null  $computer
     * @return void
     */
    public function setComputer(?\Voyager\Console\Application $computer): void
    {
        $this->computer = $computer;
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
        $this->commandPaths = array_values(array_unique(array_merge($this->commandPaths, $paths)));

        return $this;
    }

    /**
     * Set the paths that should have their Computer "routes" automatically discovered.
     *
     * @param  array  $paths
     * @return $this
     */
    public function addCommandRoutePaths(array $paths): static
    {
        $this->commandRoutePaths = array_values(array_unique(array_merge($this->commandRoutePaths, $paths)));

        return $this;
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
     * @param  \Throwable  $e
     * @return void
     */
    protected function reportException(Throwable $e): void
    {
        $this->app[ExceptionHandler::class]->report($e);
    }

    /**
     * Render the given exception.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  \Throwable  $e
     * @return void
     */
    protected function renderException(\Symfony\Component\Console\Output\OutputInterface $output, Throwable $e): void
    {
        $this->app[ExceptionHandler::class]->renderForConsole($output, $e);
    }
}
