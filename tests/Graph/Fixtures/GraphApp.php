<?php

namespace Venusian\Tests\Graph\Fixtures;

use Laudis\Neo4j\ClientBuilder;
use PHPUnit\Framework\TestCase;
use Voyager\Core\RenderedInstance;
use Venusian\Tests\Log\Fixtures\LogApp;

/**
 * The app on the local Neo4j named by the NEO4J_TEST_* variables. Its existing nodes are never
 * touched: a test works under a label of its own through label(), and tearDown() deletes the
 * nodes carrying exactly those labels.
 */
final class GraphApp
{
    /** @var list<string> */
    private static array $labels = [];

    public static function boot(): RenderedInstance
    {
        $app = LogApp::boot();

        $app['config']->set('database.connections.neo4j', [
            ...$app['config']->get('database.connections.neo4j'),
            'host' => getenv('NEO4J_TEST_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('NEO4J_TEST_PORT') ?: 7687),
            'database' => getenv('NEO4J_TEST_DATABASE') ?: 'scrapyard',
            'username' => getenv('NEO4J_TEST_USERNAME') ?: 'neo4j',
            'password' => getenv('NEO4J_TEST_PASSWORD') ?: 'neo4j',
        ]);

        return $app;
    }

    /** Whether the Bolt client is installed and the server answers. */
    public static function reachable(): bool
    {
        if (! class_exists(ClientBuilder::class)) {
            return false;
        }

        $socket = @fsockopen(getenv('NEO4J_TEST_HOST') ?: '127.0.0.1', (int) (getenv('NEO4J_TEST_PORT') ?: 7687), $errno, $errstr, 0.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    /** A label of this test's own; its nodes are deleted at tearDown. */
    public static function label(): string
    {
        return self::$labels[] = 'VenusianTest'.bin2hex(random_bytes(5));
    }

    public static function tearDown(RenderedInstance $app, TestCase $test): void
    {
        if (self::$labels !== []) {
            $db = $app['db']->connection('neo4j');

            while ($db->transactionLevel() > 0) {
                $db->rollBack();
            }

            foreach (self::$labels as $label) {
                $db->statement("MATCH (n:`{$label}`) DETACH DELETE n");
            }
        }

        self::$labels = [];
        LogApp::tearDown($app, $test);
    }
}
