<?php

namespace Voyager\Core;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Contracts\Core\Kernel as AppKernel;
use Voyager\Core\Bootstrap\ProvisionDatabase;

abstract class ApplicationKernel implements AppKernel
{
    protected array $app_bootstrappers = [];

    protected array $bootstrappers = [
        \Voyager\Core\Bootstrap\LoadEnvironmentVariables::class,
        \Voyager\Core\Bootstrap\LoadConfiguration::class,
        \Voyager\Core\Bootstrap\DeliverMailToSignals::class,
        \Voyager\Core\Bootstrap\HandleExceptions::class,
        \Voyager\Core\Bootstrap\RegisterProviders::class,
        \Voyager\Core\Bootstrap\BootProviders::class,
        \Voyager\Core\Bootstrap\ProvisionDatabase::class
    ];

    public function __construct(protected FrameworkCore $app) {}

    abstract protected function startup(): void;

    abstract protected function activate(): void;

    abstract protected function shutdown(): void;

    public function handle(array $argv): int
    {
        $this->bootstrap();

        return $this->run();
    }

    /**
     * @throws ReflectionException
     */
    public function terminate(array $argv, int $status): void
    {
        $this->app->terminate();
    }

    public function bootstrap(): void
    {
        // Before the providers register, so a provider can tell an application run apart.
        $this->app->markApplicationRunning();

        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }
    }

    public function bootstrappers(): array
    {
        return [
            ...$this->bootstrappers,
            ...$this->app_bootstrappers,
        ];
    }

    protected function run(): int
    {
        $loop = $this->app['event-loop'];

        try {
            $this->startup();
            $this->activate();

            return $loop->run();
        } finally {
            $this->shutdown();
        }
    }
}