<?php

namespace Voyager\System\Providers;

use Voyager\Console\Events\CommandFinished;
use Voyager\Console\Scheduling\Schedule;
use Voyager\Contracts\Console\Kernel as ConsoleKernel;
use Voyager\Contracts\Vessel\Vessel;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Contracts\System\Application;
use Voyager\Contracts\System\MaintenanceMode as MaintenanceModeContract;
use Voyager\Database\ConnectionInterface;
use Voyager\Database\Grammar;
use Voyager\System\Console\CliDumper;
use Voyager\System\MaintenanceModeManager;
use Voyager\Log\Events\MessageLogged;
use Voyager\Queue\Events\JobAttempted;
use Voyager\NutsAndBolts\AggregateServiceProvider;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Symfony\Component\VarDumper\Caster\StubCaster;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

class FoundationServiceProvider extends AggregateServiceProvider
{
    /**
     * The provider class names.
     *
     * @var string[]
     */
    protected array $providers = [
        // \Voyager\Testing\ParallelTestingServiceProvider::class,   // lands with Testing in wave 3
    ];

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        parent::register();

        $this->registerConsoleSchedule();
        $this->registerDumper();
        $this->registerDeferHandler();
        $this->registerMaintenanceModeManager();
    }

    /**
     * Register the console schedule implementation.
     *
     * @return void
     */
    public function registerConsoleSchedule(): void
    {
        $this->app->singleton(Schedule::class, function ($app) {
            return $app->make(ConsoleKernel::class)->resolveConsoleSchedule();
        });
    }

    /**
     * Register a var dumper (with source) to debug variables.
     *
     * @return void
     */
    public function registerDumper(): void
    {
        AbstractCloner::$defaultCasters[ConnectionInterface::class] ??= [StubCaster::class, 'cutInternals'];
        AbstractCloner::$defaultCasters[Vessel::class] ??= [StubCaster::class, 'cutInternals'];
        AbstractCloner::$defaultCasters[Dispatcher::class] ??= [StubCaster::class, 'cutInternals'];
        AbstractCloner::$defaultCasters[Grammar::class] ??= [StubCaster::class, 'cutInternals'];

        $format = $_SERVER['VAR_DUMPER_FORMAT'] ?? null;

        match (true) {
            'server' == $format => null,
            $format && 'tcp' == parse_url($format, PHP_URL_SCHEME) => null,
            default => CliDumper::register($this->app->basePath()),
        };
    }

    /**
     * Register the "defer" function termination handler.
     *
     * @return void
     */
    protected function registerDeferHandler(): void
    {
        $this->app->scoped(DeferredCallbackCollection::class);

        $this->app['events']->listen(function (CommandFinished $event) {
            app(DeferredCallbackCollection::class)->invokeWhen(fn ($callback) => app()->runningInConsole() && ($event->exitCode === 0 || $callback->always));
        });

        $this->app['events']->listen(function (JobAttempted $event) {
            if (in_array($event->connectionName, ['sync', 'deferred'])) {
                return;
            }

            app(DeferredCallbackCollection::class)->invokeWhen(fn ($callback) => ($event->successful() || $callback->always));
        });
    }

    /**
     * Register the maintenance mode manager service.
     *
     * @return void
     */
    public function registerMaintenanceModeManager(): void
    {
        $this->app->singleton(MaintenanceModeManager::class);

        $this->app->bind(
            MaintenanceModeContract::class,
            fn () => $this->app->make(MaintenanceModeManager::class)->driver()
        );
    }
}
