<?php

namespace Venusian\Tests\Log\Fixtures;

use Voyager\Core\RenderedInstance;
use Voyager\Core\VenusianVoyager;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Console\Kernel;
use PHPUnit\Framework\TestCase;
use Voyager\Core\Bootstrap\HandleExceptions;

/**
 * This repository booted as the app, the same way bin/pool-worker boots it, so the process
 * under test and its pool workers hold the same providers and config.
 */
final class LogApp
{
    /** @var list<string> log files handed out since the last tearDown() */
    private static array $files = [];

    public static function boot(): RenderedInstance
    {
        $app = VenusianVoyager::setup(dirname(__DIR__, 3))->create();
        // This package ships no .env; the test app reads an empty one of its own.
        $app->loadEnvironmentFrom('tests/Log/Fixtures/testing.env');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Points a single-file channel at a fresh file and returns its path.
     *
     * @param array<string, mixed> $config overrides for the channel's config
     */
    public static function channel(RenderedInstance $app, string $name, array $config = []): string
    {
        $path = self::$files[] = sys_get_temp_dir().'/venusian-log-'.bin2hex(random_bytes(6)).'.log';

        $app['config']->set("logging.channels.{$name}", [
            'driver' => 'single',
            'path' => $path,
            'level' => 'debug',
            'replace_placeholders' => true,
            ...$config,
        ]);

        return $path;
    }

    /** The file's lines, or none if nothing has written it yet. */
    public static function lines(string $path): array
    {
        return is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    }

    /** Stops the pools, removes the files, and hands PHPUnit back the error handlers the boot replaced. */
    public static function tearDown(RenderedInstance $app, TestCase $test): void
    {
        foreach (['thread-pool', 'process-pool'] as $pool) {
            if ($app->isBound($pool)) {
                $app->get($pool)->shutDown();
            }
        }

        foreach (self::$files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        self::$files = [];
        HandleExceptions::flushState($test);
        ControlPanel::setInstance(null);
    }
}
