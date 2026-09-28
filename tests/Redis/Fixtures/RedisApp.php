<?php

namespace Venusian\Tests\Redis\Fixtures;

use PHPUnit\Framework\TestCase;
use Voyager\Core\RenderedInstance;
use Voyager\Core\VenusianVoyager;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Console\Kernel;
use Voyager\Core\Bootstrap\HandleExceptions;

/**
 * This repository booted as the app, on the local Redis's database 15 with a key prefix of its own,
 * so a run never touches keys it didn't write.
 */
final class RedisApp
{
    public const int DATABASE = 15;

    public static function boot(): RenderedInstance
    {
        $app = VenusianVoyager::setup(dirname(__DIR__, 3))->create();
        // This package ships no .env; the test app reads an empty one of its own.
        $app->loadEnvironmentFrom('tests/Log/Fixtures/testing.env');
        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('database.redis.default.database', self::DATABASE);
        $app['config']->set('database.redis.options.prefix', 'venusian_test_'.bin2hex(random_bytes(4)).':');

        return $app;
    }

    /** Whether a Redis answers on the default connection's host and port. */
    public static function reachable(): bool
    {
        $socket = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 0.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /** Deletes every key this app's prefix wrote, then hands PHPUnit back its error handlers. */
    public static function tearDown(RenderedInstance $app, TestCase $test): void
    {
        $connection = $app->get('redis')->connection();
        $prefix = $app['config']->get('database.redis.options.prefix');
        $keys = $connection->client()->keys('*');

        foreach ($keys as $key) {
            $connection->client()->del(substr($key, strlen($prefix)));
        }

        HandleExceptions::flushState($test);
        ControlPanel::setInstance(null);
    }
}
