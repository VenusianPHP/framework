<?php

namespace Voyager\Core\Providers;

use ReflectionClass;
use Voyager\Queue\Console\ClearCommand as QueueClearCommand;
use Voyager\Queue\Console\FlushFailedCommand as FlushFailedQueueCommand;
use Voyager\Queue\Console\ForgetFailedCommand as ForgetFailedQueueCommand;
use Voyager\Queue\Console\ListenCommand as QueueListenCommand;
use Voyager\Queue\Console\ListFailedCommand as ListFailedQueueCommand;
use Voyager\Queue\Console\MonitorCommand as QueueMonitorCommand;
use Voyager\Queue\Console\PauseCommand as QueuePauseCommand;
use Voyager\Queue\Console\PruneBatchesCommand as QueuePruneBatchesCommand;
use Voyager\Queue\Console\PruneFailedJobsCommand as QueuePruneFailedJobsCommand;
use Voyager\Queue\Console\RestartCommand as QueueRestartCommand;
use Voyager\Queue\Console\ResumeCommand as QueueResumeCommand;
use Voyager\Queue\Console\RetryBatchCommand as QueueRetryBatchCommand;
use Voyager\Queue\Console\RetryCommand as QueueRetryCommand;
use Voyager\Queue\Console\WorkCommand as QueueWorkCommand;
use ReflectionException;
use Voyager\Console\ConsoleSignals;
use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\Concurrency\Console\InvokeSerializedClosureCommand;
use Voyager\Core\Console\ClassMakeCommand;
use Voyager\Core\Console\ConfigCacheCommand;
use Voyager\Core\Console\ConfigClearCommand;
use Voyager\Core\Console\ConfigMakeCommand;
use Voyager\Core\Console\ConfigPublishCommand;
use Voyager\Core\Console\ConsoleMakeCommand;
use Voyager\Core\Console\EnumMakeCommand;
use Voyager\Core\Console\EnvironmentCommand;
use Voyager\Core\Console\EnvironmentDecryptCommand;
use Voyager\Core\Console\EnvironmentEncryptCommand;
use Voyager\Core\Console\EventGenerateCommand;
use Voyager\Core\Console\EventListCommand;
use Voyager\Core\Console\EventMakeCommand;
use Voyager\Core\Console\ExceptionMakeCommand;
use Voyager\Core\Console\FactoryMakeCommand;
use Voyager\Core\Console\GigMakeCommand;
use Voyager\Core\Console\InterfaceMakeCommand;
use Voyager\Core\Console\JobMakeCommand;
use Voyager\Core\Console\JobMiddlewareMakeCommand;
use Voyager\Core\Console\ListenerMakeCommand;
use Voyager\Core\Console\ObserverMakeCommand;
use Voyager\Core\Console\PackageDiscoverCommand;
use Voyager\Core\Console\ProviderMakeCommand;
use Voyager\Core\Console\SignalCacheCommand;
use Voyager\Core\Console\SignalClearCommand;
use Voyager\Core\Console\StubPublishCommand;
use Voyager\Core\Console\TestMakeCommand;
use Voyager\Core\Console\TraitMakeCommand;
use Voyager\Core\Console\VendorPublishCommand;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\ServiceProvider;

class ComputerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $commands = [
        //'About' => AboutCommand::class,
        'ConfigCache' => ConfigCacheCommand::class,
        'ConfigClear' => ConfigClearCommand::class,
        'Environment' => EnvironmentCommand::class,
        'EnvironmentDecrypt' => EnvironmentDecryptCommand::class,
        'EnvironmentEncrypt' => EnvironmentEncryptCommand::class,
        'PackageDiscover' => PackageDiscoverCommand::class,
        'InvokeSerializedClosure' => InvokeSerializedClosureCommand::class,
        'SignalCache' => SignalCacheCommand::class,
        'SignalClear' => SignalClearCommand::class,
        'SignalList' => EventListCommand::class,
        'QueueClear' => QueueClearCommand::class,
        'QueueFailed' => ListFailedQueueCommand::class,
        'QueueFlush' => FlushFailedQueueCommand::class,
        'QueueForget' => ForgetFailedQueueCommand::class,
        'QueueListen' => QueueListenCommand::class,
        'QueueMonitor' => QueueMonitorCommand::class,
        'QueuePause' => QueuePauseCommand::class,
        'QueuePruneBatches' => QueuePruneBatchesCommand::class,
        'QueuePruneFailedJobs' => QueuePruneFailedJobsCommand::class,
        'QueueRestart' => QueueRestartCommand::class,
        'QueueResume' => QueueResumeCommand::class,
        'QueueRetry' => QueueRetryCommand::class,
        'QueueRetryBatch' => QueueRetryBatchCommand::class,
        'QueueWork' => QueueWorkCommand::class,
        // queue:table, queue:failed-table, queue:batches-table need MigrationGeneratorCommand: Database wave
    ];

    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected array $dev_commands = [
        'ClassMake' => ClassMakeCommand::class,
        'ConfigMake' => ConfigMakeCommand::class,
        'ConfigPublish' => ConfigPublishCommand::class,
        'ConsoleMake' => ConsoleMakeCommand::class,
        'EnumMake' => EnumMakeCommand::class,
        'EventGenerate' => EventGenerateCommand::class,
        'EventMake' => EventMakeCommand::class,
        'ExceptionMake' => ExceptionMakeCommand::class,
        'FactoryMake' => FactoryMakeCommand::class,
        'InterfaceMake' => InterfaceMakeCommand::class,
        'GigMake' => GigMakeCommand::class,
        //'JobMake' => JobMakeCommand::class,
        //'JobMiddlewareMake' => JobMiddlewareMakeCommand::class,
        'ListenerMake' => ListenerMakeCommand::class,
        'ObserverMake' => ObserverMakeCommand::class,
        'ProviderMake' => ProviderMakeCommand::class,

        'StubPublish' => StubPublishCommand::class,
        'TestMake' => TestMakeCommand::class,
        'TraitMake' => TraitMakeCommand::class,
        'VendorPublish' => VendorPublishCommand::class,
    ];

    /**
     * Register the service provider.
     *
     * @return void
     * @throws ReflectionException
     */
    public function register(): void
    {
        $this->registerCommands(array_merge(
            $this->commands,
            $this->dev_commands
        ));

        ConsoleSignals::resolveAvailabilityUsing(function () {
            return ! $this->app->runningUnitTests()
                && extension_loaded('pcntl');
        });
    }

    /**
     * Register the given commands.
     *
     * @param array $commands
     * @return void
     * @throws ReflectionException
     */
    protected function registerCommands(array $commands): void
    {
        foreach ($commands as $command_name => $command) {
            $method = "register{$command_name}Command";

            if (method_exists($this, $method)) {
                $this->{$method}();
            } elseif ($this->wantsFilesystem($command)) {
                $this->app->registerSingleton($command, fn () => new $command(new Filesystem));
            } else {
                $this->app->registerSingleton($command);
            }
        }

        $this->commands(array_values($commands));
    }

    /**
     * Deferred providers only boot when something they provide is needed.
     * The console kernel loads every deferred provider before it builds the
     * command list, so these class names are what put the commands on it.
     *
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return array_values(array_merge($this->commands, $this->dev_commands));
    }

    /** Make-commands and a few others take the filesystem in their constructor. */
    protected function registerQueueListenCommand(): void
    {
        $this->app->registerSingleton(QueueListenCommand::class, fn ($app) => new QueueListenCommand($app['queue.listener']));
    }

    protected function registerQueueMonitorCommand(): void
    {
        $this->app->registerSingleton(QueueMonitorCommand::class, fn ($app) => new QueueMonitorCommand($app['queue'], $app['signals']));
    }

    protected function registerQueueRestartCommand(): void
    {
        $this->app->registerSingleton(QueueRestartCommand::class, fn ($app) => new QueueRestartCommand($app['cache.store']));
    }

    protected function registerQueueWorkCommand(): void
    {
        $this->app->registerSingleton(QueueWorkCommand::class, fn ($app) => new QueueWorkCommand($app['queue.worker'], $app['cache.store']));
    }

    private function wantsFilesystem(string $command): bool
    {
        $constructor = (new ReflectionClass($command))->getConstructor();

        return ! is_null($constructor) && $constructor->getNumberOfRequiredParameters() > 0;
    }

}