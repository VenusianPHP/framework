<?php

namespace Venusian\Tests\Http\Fixtures;

use PHPUnit\Framework\TestCase;
use Voyager\Core\RenderedInstance;
use Voyager\Core\VenusianVoyager;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Console\Kernel;
use Voyager\Core\Bootstrap\HandleExceptions;

/**
 * This repository booted as the app, and a `php -S` server running tests/Http/Fixtures/server.php
 * with workers of its own, so concurrent requests really are answered concurrently.
 */
final class HttpApp
{
    /** @var resource|null */
    private static $server = null;

    private static int $port = 0;

    public static function boot(string $driver): RenderedInstance
    {
        $app = VenusianVoyager::setup(dirname(__DIR__, 3))->create();
        // This package ships no .env; the test app reads an empty one of its own.
        $app->loadEnvironmentFrom('tests/Log/Fixtures/testing.env');
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set('http.async.default', $driver);

        return $app;
    }

    public static function url(string $path): string
    {
        self::serve();

        return 'http://127.0.0.1:'.self::$port.$path;
    }

    /** A port nothing listens on. */
    public static function closedPortUrl(): string
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        return "http://127.0.0.1:{$port}/";
    }

    public static function tearDown(TestCase $test): void
    {
        HandleExceptions::flushState($test);
        ControlPanel::setInstance(null);
    }

    /**
     * Kills the workers PHP_CLI_SERVER_WORKERS forked, then the server: the workers outlive a stopped
     * master. SIGKILL, because a server spawned while a loop watched SIGTERM inherits it ignored.
     */
    public static function stopServer(): void
    {
        if (is_resource(self::$server)) {
            $master = proc_get_status(self::$server)['pid'];
            exec('pkill -KILL -P '.(int) $master);
            proc_terminate(self::$server, SIGKILL);
            proc_close(self::$server);
        }

        self::$server = null;
    }

    private static function serve(): void
    {
        if (is_resource(self::$server)) {
            return;
        }

        $probe = stream_socket_server('tcp://127.0.0.1:0');
        self::$port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.self::$port, __DIR__.'/server.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            [...getenv(), 'PHP_CLI_SERVER_WORKERS' => '8'],
        );

        // Up once it accepts a connection.
        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            if ($socket = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1)) {
                fclose($socket);
                return;
            }

            usleep(20_000);
        }

        throw new \RuntimeException('The fixture HTTP server did not come up on port '.self::$port.'.');
    }
}
