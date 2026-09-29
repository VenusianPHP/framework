<?php

namespace Venusian\Tests\Filesystem\Fixtures;

use PHPUnit\Framework\TestCase;
use Voyager\Core\RenderedInstance;
use Voyager\Core\VenusianVoyager;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Console\Kernel;
use Voyager\Core\Bootstrap\HandleExceptions;

/**
 * This repository booted as the app, its local and public disks rooted in a fresh directory, so a
 * run only touches what it wrote.
 */
final class FilesApp
{
    public static function boot(): RenderedInstance
    {
        $app = VenusianVoyager::setup(dirname(__DIR__, 3))->create();
        // This package ships no .env; the test app reads an empty one of its own.
        $app->loadEnvironmentFrom('tests/Log/Fixtures/testing.env');
        $app->make(Kernel::class)->bootstrap();

        $root = sys_get_temp_dir().'/venusian-files-'.bin2hex(random_bytes(6));
        mkdir($root);
        $app['config']->set('filesystems.disks.local.root', $root.'/local');
        $app['config']->set('filesystems.disks.public.root', $root.'/public');
        $app->registerInstance('test.root', $root);

        return $app;
    }

    /** Removes the run's directory, stops the pools, and hands PHPUnit back its error handlers. */
    public static function tearDown(RenderedInstance $app, TestCase $test): void
    {
        foreach (['thread-workers', 'process-workers'] as $pool) {
            if ($app->isBound($pool)) {
                $app->get($pool)->shutDown();
            }
        }

        $app['files']->deleteDirectory($app['test.root']);
        $app['files']->deleteDirectory($app->storagePath('framework/testing'));

        // fake() made storage/framework to hold its disks; it goes once they're gone.
        if (is_dir($framework = $app->storagePath('framework')) && $app['files']->isEmptyDirectory($framework)) {
            rmdir($framework);
        }

        HandleExceptions::flushState($test);
        ControlPanel::setInstance(null);
    }
}
