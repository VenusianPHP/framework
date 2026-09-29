<?php

namespace Venusian\Tests\Database\Fixtures;

use PHPUnit\Framework\TestCase;
use Voyager\Core\RenderedInstance;
use Voyager\Database\Connection;
use Venusian\Tests\Log\Fixtures\LogApp;

/**
 * The app on three databases: a fresh SQLite file, and the local MySQL and Postgres named by the
 * DB_TEST_* variables. Their existing tables are never touched: a test creates tables under a
 * name of its own through table(), and tearDown() drops exactly those.
 */
final class DatabaseApp
{
    /** @var list<array{connection: string, table: string}> */
    private static array $tables = [];

    private static ?string $sqlite = null;

    public static function boot(): RenderedInstance
    {
        $app = LogApp::boot();

        self::$sqlite = sys_get_temp_dir().'/venusian-test-'.bin2hex(random_bytes(6)).'.sqlite';
        touch(self::$sqlite);

        $app['config']->set('database.connections.sqlite.database', self::$sqlite);
        $app['config']->set('database.connections.mysql', [
            ...$app['config']->get('database.connections.mysql'),
            'host' => getenv('DB_TEST_MYSQL_HOST') ?: '127.0.0.1',
            'database' => getenv('DB_TEST_MYSQL_DATABASE') ?: 'blog',
            'username' => getenv('DB_TEST_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('DB_TEST_MYSQL_PASSWORD') ?: '',
        ]);
        $app['config']->set('database.connections.pgsql', [
            ...$app['config']->get('database.connections.pgsql'),
            'host' => getenv('DB_TEST_PGSQL_HOST') ?: '127.0.0.1',
            'database' => getenv('DB_TEST_PGSQL_DATABASE') ?: 'framework',
            'username' => getenv('DB_TEST_PGSQL_USERNAME') ?: 'postgres',
            'password' => getenv('DB_TEST_PGSQL_PASSWORD') ?: '',
        ]);

        return $app;
    }

    /** Whether the server behind the connection answers. SQLite always does. */
    public static function reachable(string $connection): bool
    {
        $port = ['mysql' => 3306, 'pgsql' => 5432][$connection] ?? null;

        if (is_null($port)) {
            return true;
        }

        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /**
     * A table of this test's own on the connection, created by $blueprint and dropped at tearDown.
     *
     * @param \Closure(\Voyager\Database\Schema\Blueprint): void $blueprint
     */
    public static function table(RenderedInstance $app, string $connection, \Closure $blueprint): string
    {
        $table = 'venusian_test_'.bin2hex(random_bytes(5));
        $app['db']->connection($connection)->getSchemaBuilder()->create($table, $blueprint);
        self::$tables[] = ['connection' => $connection, 'table' => $table];

        return $table;
    }

    public static function tearDown(RenderedInstance $app, TestCase $test): void
    {
        foreach (array_reverse(self::$tables) as ['connection' => $connection, 'table' => $table]) {
            /** @var Connection $db */
            $db = $app['db']->connection($connection);

            while ($db->transactionLevel() > 0) {
                $db->rollBack();
            }

            $db->getSchemaBuilder()->dropIfExists($table);
        }

        self::$tables = [];

        if (self::$sqlite && is_file(self::$sqlite)) {
            unlink(self::$sqlite);
        }

        self::$sqlite = null;
        LogApp::tearDown($app, $test);
    }
}
