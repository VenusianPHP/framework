<?php
declare(strict_types=1);
namespace Voyager\Core\Sketches;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Voyager\Console\ComputerConsoleInstance;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Contracts\Sketches\Kernel as KernelContract;
use Voyager\Contracts\Sketches\SketchRegistry;
use Voyager\Sketches\Console\RunSketchCommand;
use Voyager\Sketches\DiscoverSketches;
use Voyager\Sketches\Signals\SketchFinished;
use Voyager\Sketches\Signals\SketchStarting;

class Kernel implements KernelContract
{
    protected array $bootstrappers = [
        \Voyager\Core\Bootstrap\LoadEnvironmentVariables::class,
        \Voyager\Core\Bootstrap\LoadConfiguration::class,
        \Voyager\Core\Bootstrap\HandleExceptions::class,
        \Voyager\Core\Bootstrap\RegisterProviders::class,
        \Voyager\Core\Bootstrap\BootProviders::class,
    ];

    protected array $sketches = [];
    protected array $sketch_paths = [];
    protected ?string $root_namespace = null;
    protected ?string $root_path = null;
    protected ?ComputerConsoleInstance $rocket = null;
    protected ?EventDispatcherInterface $symfony_dispatcher = null;

    public function __construct(protected readonly FrameworkCore $app)
    {
        if (! defined('ROCKET_BINARY')) {
            define('ROCKET_BINARY', 'launch');
        }

        $app->booted(function () use ($app) {
            if (! $app->runningUnitTests()) {
                $this->rerouteSymfonyCommandEvents();
            }
        });
    }

    public function addSketches(array $sketches): static
    {
        $this->sketches = array_values(array_unique(array_merge($this->sketches, $sketches)));
        return $this;
    }

    public function addSketchPaths(array $paths): static
    {
        $this->sketch_paths = array_values(array_unique(array_merge($this->sketch_paths, $paths)));
        return $this;
    }

    public function discoverUsing(string $root_namespace, string $root_path): static
    {
        $this->root_namespace = $root_namespace;
        $this->root_path = $root_path;
        return $this;
    }

    public function handle(InputInterface $input, ?OutputInterface $output = null): int
    {
        $output ??= new ConsoleOutput;

        try {
            $this->bootstrap();
            return $this->getRocket()->run($input, $output);
        } catch (Throwable $e) {
            $this->reportException($e);
            $this->renderException($output, $e);
            return 1;
        }
    }

    public function terminate(InputInterface $input, int $status): void
    {
        $this->app->terminate();
    }

    public function bootstrap(): void
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers);
        }
        $this->app->loadDeferredProviders();
    }

    public function rerouteSymfonyCommandEvents(): static
    {
        if (is_null($this->symfony_dispatcher)) {
            $this->symfony_dispatcher = new EventDispatcher;
            $this->symfony_dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) {
                $this->app['signals']->dispatch(new SketchStarting($event->getCommand()?->getName() ?? '', $event->getInput(), $event->getOutput()));
            });
            $this->symfony_dispatcher->addListener(ConsoleEvents::TERMINATE, function (ConsoleTerminateEvent $event) {
                $this->app['signals']->dispatch(new SketchFinished($event->getCommand()?->getName() ?? '', $event->getInput(), $event->getOutput(), $event->getExitCode()));
            });
        }
        return $this;
    }

    protected function getRocket(): ComputerConsoleInstance
    {
        if (is_null($this->rocket)) {
            $registry = $this->registry();
            $this->rocket = new ComputerConsoleInstance($this->app, $this->app->version());
            $this->rocket->setName('Rocket');

            foreach ($registry->all() as $name => $class) {
                $defaults = (new \ReflectionClass($class))->getDefaultProperties();
                $this->rocket->add(new RunSketchCommand($name, is_string($defaults['description'] ?? null) ? $defaults['description'] : ''));
            }

            if ($this->symfony_dispatcher instanceof EventDispatcher) {
                $this->rocket->setDispatcher($this->symfony_dispatcher);
            }
        }
        return $this->rocket;
    }

    protected function registry(): SketchRegistry
    {
        $registry = $this->app->make(SketchRegistry::class);
        $classes = array_merge(
            $this->sketches,
            (array) $this->app['config']->get('sketches.load', []),
            DiscoverSketches::within(
                $this->sketch_paths,
                $this->root_namespace ?? $this->app->getNamespace(),
                $this->root_path ?? $this->app->path(),
            ),
        );
        foreach (array_unique($classes) as $class) {
            if (! $registry->has(\Voyager\Sketches\SketchRegistry::nameFor($class))) {
                $registry->register($class);
            }
        }
        return $registry;
    }

    protected function reportException(Throwable $e): void
    {
        if ($this->app->isBound(ExceptionHandler::class)) {
            $this->app->make(ExceptionHandler::class)->report($e);
        }
    }

    protected function renderException(OutputInterface $output, Throwable $e): void
    {
        if ($this->app->isBound(ExceptionHandler::class)) {
            $this->app->make(ExceptionHandler::class)->renderForConsole($output, $e);
            return;
        }
        $output->writeln('<error>'.$e->getMessage().'</error>');
    }
}
