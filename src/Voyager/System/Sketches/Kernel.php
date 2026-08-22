<?php

namespace Voyager\System\Sketches;

use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Contracts\Sketches\Kernel as KernelContract;
use Voyager\Contracts\Sketches\SketchRegistry;
use Voyager\Contracts\System\Application;
use Voyager\Sketches\Application as Runner;
use Voyager\Sketches\SketchRunner;
use Voyager\System\Events\Terminating;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class Kernel implements KernelContract
{
    protected ?Runner $runner = null;

    /**
     * @var array<int, class-string>
     */
    protected array $bootstrappers = [
        \Voyager\System\Bootstrap\LoadEnvironmentVariables::class,
        \Voyager\System\Bootstrap\LoadConfiguration::class,
        \Voyager\System\Bootstrap\HandleExceptions::class,
        \Voyager\System\Bootstrap\RegisterMagicAliases::class,
        \Voyager\System\Bootstrap\RegisterProviders::class,
        \Voyager\System\Bootstrap\BootProviders::class,
    ];

    /**
     * Global runner middleware stack (class-strings).
     *
     * @var array<int, class-string|callable|object>
     */
    protected array $middleware = [];

    public function __construct(
        protected Application $app,
    ) {
        if (! defined('RUNNER_BINARY')) {
            define('RUNNER_BINARY', 'runner');
        }
    }

    public function handle(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->bootstrap();

            return $this->getRunner()->run($input, $output);
        } catch (Throwable $e) {
            $this->reportException($e);
            $this->renderException($output, $e);

            return 1;
        }
    }

    public function terminate(InputInterface $input, int $status): void
    {
        if ($this->app->bound(\Voyager\Contracts\Events\Dispatcher::class)) {
            $this->app['events']->dispatch(new Terminating);
        }

        $this->app->terminate();
    }

    public function bootstrap(): void
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers);
        }

        $this->app->loadDeferredProviders();
    }

    public function getRunner(): Runner
    {
        if (is_null($this->runner)) {
            $this->runner = new Runner(
                container: $this->app,
                registry: $this->app->make(SketchRegistry::class),
                runner: $this->app->make(SketchRunner::class),
                version: $this->app->version(),
                globalMiddleware: $this->middleware(),
            );
        }

        return $this->runner;
    }

    /**
     * @return array<int, class-string|callable|object>
     */
    protected function middleware(): array
    {
        $configured = $this->app['config']->get('sketches.middleware', []);

        return array_values(array_merge(
            $this->middleware,
            is_array($configured) ? $configured : [],
        ));
    }

    protected function reportException(Throwable $e): void
    {
        if ($this->app->bound(ExceptionHandler::class)) {
            $this->app->make(ExceptionHandler::class)->report($e);
        }
    }

    protected function renderException(OutputInterface $output, Throwable $e): void
    {
        if ($this->app->bound(ExceptionHandler::class)) {
            $this->app->make(ExceptionHandler::class)->renderForConsole($output, $e);

            return;
        }

        $output->writeln('<error>'.$e->getMessage().'</error>');
    }
}
