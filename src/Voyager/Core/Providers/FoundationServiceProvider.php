<?php

namespace Voyager\Core\Providers;

use ReflectionException;
use Voyager\Core\Console\CliDumper;
use Throwable;
use Voyager\Console\Signals\CommandFinished;
use Voyager\Console\Scheduling\Schedule;
use Voyager\Contracts\Console\Kernel as ConsoleKernel;
use Voyager\Contracts\Vessel\TheServiceContainer;
use Voyager\Contracts\Signals\SignalDispatcher;
use Voyager\Database\ConnectionInterface;
use Voyager\Database\Grammar;
use Voyager\Queue\Signals\JobAttempted;
use Voyager\Log\Signals\MessageLogged;
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
     * @throws ReflectionException
     * @throws Throwable
     */
    public function register(): void
    {
        parent::register();

        //$this->registerConsoleSchedule();
        $this->registerDumper();
        $this->registerDeferHandler();
    }

    /**
     * Register the console schedule implementation.
     *
     * @return void
     * @throws ReflectionException
     */
    public function registerConsoleSchedule(): void
    {
        $this->app->registerSingleton(Schedule::class, function ($app) {
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
        //AbstractCloner::$defaultCasters[ConnectionInterface::class] ??= [StubCaster::class, 'cutInternals'];
        AbstractCloner::$defaultCasters[TheServiceContainer::class] ??= [StubCaster::class, 'cutInternals'];
        AbstractCloner::$defaultCasters[SignalDispatcher::class] ??= [StubCaster::class, 'cutInternals'];
        //AbstractCloner::$defaultCasters[Grammar::class] ??= [StubCaster::class, 'cutInternals'];

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
     * @throws \ReflectionException
     * @throws \Throwable
     */
    protected function registerDeferHandler(): void
    {
        $this->app->scoped(DeferredCallbackCollection::class);

        $this->app['signals']->listen(function (CommandFinished $event) {
            app(DeferredCallbackCollection::class)->invokeWhen(fn ($callback) => $event->exitCode === 0 || $callback->always);
        });

        $this->app['signals']->listen(function (JobAttempted $event) {
            if (in_array($event->connection_name, ['sync', 'deferred'])) {
                return;
            }

            app(DeferredCallbackCollection::class)->invokeWhen(fn ($callback) => ($event->successful() || $callback->always));
        });
    }
}
