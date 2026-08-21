<?php

namespace Voyager\System;

use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Voyager\Concurrency\ProcessDriver;
use Voyager\Encryption\EncryptionServiceProvider;
use Voyager\System\Bootstrap\LoadConfiguration;
use Voyager\System\Bootstrap\LoadEnvironmentVariables;
use Throwable;

class ComposerScripts
{
    /**
     * Handle the post-install Composer event.
     *
     * @param  \Composer\Script\Event  $event
     * @return void
     */
    public static function postInstall(Event $event): void
    {
        require_once $event->getComposer()->getConfig()->get('vendor-dir').'/autoload.php';

        static::clearCompiled();
    }

    /**
     * Handle the post-update Composer event.
     *
     * @param  \Composer\Script\Event  $event
     * @return void
     */
    public static function postUpdate(Event $event): void
    {
        require_once $event->getComposer()->getConfig()->get('vendor-dir').'/autoload.php';

        static::clearCompiled();
    }

    /**
     * Handle the post-autoload-dump Composer event.
     *
     * @param  \Composer\Script\Event  $event
     * @return void
     */
    public static function postAutoloadDump(Event $event): void
    {
        require_once $event->getComposer()->getConfig()->get('vendor-dir').'/autoload.php';

        static::clearCompiled();
    }

    /**
     * Handle the pre-package-uninstall Composer event.
     *
     * @param  \Composer\Installer\PackageEvent  $event
     * @return void
     */
    public static function prePackageUninstall(PackageEvent $event): void
    {
        // Package uninstall events are only applicable when uninstalling packages in dev environments...
        if (! $event->isDevMode()) {
            return;
        }

        $eventName = null;
        try {
            require_once $event->getComposer()->getConfig()->get('vendor-dir').'/autoload.php';

            $venusian = new Application(getcwd());

            $venusian->bootstrapWith([
                LoadEnvironmentVariables::class,
                LoadConfiguration::class,
            ]);

            // Ensure we can encrypt our serializable closure...
            (new EncryptionServiceProvider($venusian))->register();

            $name = $event->getOperation()->getPackage()->getName();
            $eventName = "composer_package.{$name}:pre_uninstall";

            $venusian->make(ProcessDriver::class)->run(
                static fn () => app()['events']->dispatch($eventName)
            );
        } catch (Throwable $e) {
            // Ignore any errors to allow the package removal to complete...
            $event->getIO()->write('There was an error dispatching or handling the ['.($eventName ?? 'unknown').'] event. Continuing with package removal...');
            $event->getIO()->writeError('Exception message: '.$e->getMessage(), verbosity: IOInterface::VERBOSE); // @phpstan-ignore class.notFound (Composer exists if this is running)
        }
    }

    /**
     * Clear the cached Venusian bootstrapping files.
     *
     * @return void
     */
    protected static function clearCompiled(): void
    {
        $venusian = new Application(getcwd());

        if (is_file($configPath = $venusian->getCachedConfigPath())) {
            @unlink($configPath);
        }

        if (is_file($servicesPath = $venusian->getCachedServicesPath())) {
            @unlink($servicesPath);
        }

        if (is_file($packagesPath = $venusian->getCachedPackagesPath())) {
            @unlink($packagesPath);
        }
    }
}
