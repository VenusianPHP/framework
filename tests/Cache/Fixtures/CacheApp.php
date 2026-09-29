<?php

namespace Venusian\Tests\Cache\Fixtures;

use PHPUnit\Framework\TestCase;
use Voyager\Core\RenderedInstance;
use Voyager\Core\VenusianVoyager;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Console\Kernel;
use Voyager\Core\Bootstrap\HandleExceptions;

/**
 * This repository booted as the app, its file store in a fresh directory, its database store on a
 * fresh SQLite file, its redis store on the local Redis's database 15 under a key prefix of its
 * own, so a run only touches what it wrote.
 */
final class CacheApp
{
    public const int DATABASE = 15;

    public static function boot(): RenderedInstance
    {
        $app = VenusianVoyager::setup(dirname(__DIR__, 3))->create();
        // This package ships no .env; the test app reads an empty one of its own.
        $app->loadEnvironmentFrom('tests/Log/Fixtures/testing.env');
        $app->make(Kernel::class)->bootstrap();

        $directory = sys_get_temp_dir().'/venusian-cache-'.bin2hex(random_bytes(6));
        $app['config']->set('cache.stores.file.path', $directory);
        $app['config']->set('cache.stores.file.lock_path', $directory);
        $app['config']->set('database.redis.cache.database', self::DATABASE);
        $app['config']->set('database.redis.default.database', self::DATABASE);
        $app['config']->set('database.redis.options.prefix', 'venusian_test_'.bin2hex(random_bytes(4)).':');

        // the database store on a SQLite file of this run's own, its tables made by cache:table's stub
        $sqlite = $directory.'.sqlite';
        touch($sqlite);
        $app['config']->set('database.connections.sqlite.database', $sqlite);
        $app['config']->set('cache.stores.database.connection', 'sqlite');
        self::migrateCacheTables($directory.'-cache-migration.php');

        return $app;
    }

    /** Runs cache:table's migration, as the command would write it, on the default SQLite file. */
    public static function migrateCacheTables(string $path): void
    {
        file_put_contents($path, str_replace('{{table}}', 'cache', file_get_contents(dirname(__DIR__, 3).'/src/Voyager/Cache/Console/stubs/cache.stub')));

        try {
            (require $path)->up();
        } finally {
            unlink($path);
        }
    }

    public static function redisReachable(): bool
    {
        $socket = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 0.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /** Removes the file store's directory and every Redis key the app's prefix wrote. */
    public static function tearDown(RenderedInstance $app, TestCase $test): void
    {
        $directory = $app['config']->get('cache.stores.file.path');

        if (is_dir($directory)) {
            $app['files']->deleteDirectory($directory);
        }

        if (is_file($directory.'.sqlite')) {
            unlink($directory.'.sqlite');
        }

        if ($app->isBound('redis') && self::redisReachable()) {
            $connection = $app->get('redis')->connection('cache');
            $prefix = $app['config']->get('database.redis.options.prefix');

            foreach ($connection->client()->keys('*') as $key) {
                $connection->client()->del(substr($key, strlen($prefix)));
            }
        }

        foreach (['thread-workers', 'process-workers'] as $pool) {
            if ($app->isBound($pool)) {
                $app->get($pool)->shutDown();
            }
        }

        HandleExceptions::flushState($test);
        ControlPanel::setInstance(null);
    }
}
