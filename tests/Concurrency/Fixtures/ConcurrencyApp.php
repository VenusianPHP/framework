<?php

namespace Venusian\Tests\Concurrency\Fixtures;

use PHPUnit\Framework\TestCase;
use Voyager\Core\RenderedInstance;
use Voyager\Core\VenusianVoyager;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\Console\Kernel;
use Voyager\Core\Bootstrap\HandleExceptions;
use Voyager\Concurrency\ConcurrencyResults;

/**
 * An app to run tasks from. The pool driver's tests boot this repository, whose config turns the
 * process workers on. The process driver's boot the app `php computer` runs in: its tasks run
 * through that app's invoke-serialized-closure command.
 */
final class ConcurrencyApp
{
    public static function boot(): RenderedInstance
    {
        $app = VenusianVoyager::setup(dirname(__DIR__, 3))->create();
        // This package ships no .env; the test app reads an empty one of its own.
        $app->loadEnvironmentFrom('tests/Log/Fixtures/testing.env');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public static function bootComputerApp(): RenderedInstance
    {
        $app = VenusianVoyager::setup(self::computerAppPath())->create();
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /** The app with a `computer` script: VENUSIAN_APP_PATH, or venusian_copy beside this repository. */
    public static function computerAppPath(): string
    {
        return getenv('VENUSIAN_APP_PATH') ?: dirname(__DIR__, 4).'/venusian_copy';
    }

    public static function hasComputerApp(): bool
    {
        return is_file(self::computerAppPath().'/computer');
    }

    /** Runs the loop until $name's results arrive as mail, or ten seconds pass. */
    public static function mail(RenderedInstance $app, string $name): ?ConcurrencyResults
    {
        $heard = null;
        $loop = $app->get(Loop::class);

        $app->get('signals')->listen("concurrency:{$name}", function (ConcurrencyResults $results) use (&$heard, $loop) {
            $heard = $results;
            $loop->stop();
        });

        $loop->at(10, fn () => $loop->stop());
        $loop->run();

        return $heard;
    }

    public static function tearDown(RenderedInstance $app, TestCase $test): void
    {
        foreach (['thread-workers', 'process-workers'] as $pool) {
            if ($app->isBound($pool)) {
                $app->get($pool)->shutDown();
            }
        }

        HandleExceptions::flushState($test);
        ControlPanel::setInstance(null);
    }
}
