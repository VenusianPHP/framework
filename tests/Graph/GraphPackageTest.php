<?php

use Voyager\Contracts\Vessel\TheServiceContainer;
use Voyager\Database\Connectors\ConnectionFactory;
use Voyager\Database\DatabaseManager;
use Voyager\Database\Instrument\Model as SqlInstrumentModel;
use Voyager\Graph\Database\Connectors\Neo4jConnector;
use Voyager\Graph\Database\Neo4jConnection;
use Voyager\Graph\GraphServiceProvider;
use Voyager\Graph\Instrument\Model as GraphModel;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Core\RenderedInstance;
use Voyager\Core\DefaultProviders;

test('graph package exposes neo4j connector and graph instrument model', function () {
    expect(class_exists(Neo4jConnector::class))->toBeTrue()
        ->and(class_exists(Neo4jConnection::class))->toBeTrue()
        ->and(is_subclass_of(GraphModel::class, SqlInstrumentModel::class))->toBeTrue()
        ->and(function_exists('cypher'))->toBeTrue()
        ->and(function_exists('cypher_one'))->toBeTrue()
        ->and(function_exists('cypher_run'))->toBeTrue()
        ->and(function_exists('neo4j_connection'))->toBeTrue();
});

test('graph service provider is optional and not a default provider', function () {
    expect(DefaultProviders::make()->toArray())->not->toContain(GraphServiceProvider::class)
        ->and(is_subclass_of(GraphServiceProvider::class, ServiceProvider::class))->toBeTrue();
});

test('graph service provider registers neo4j database extension', function () {
    $app = new RenderedInstance(sys_get_temp_dir());

    $app->registerSingleton('db.factory', fn (TheServiceContainer $app) => new ConnectionFactory($app));
    $app->registerSingleton('db', fn (TheServiceContainer $app) => new DatabaseManager($app, $app['db.factory']));
    $app->registerInstance('config', new class
    {
        public function get(string $key, mixed $default = null): mixed
        {
            return $default;
        }

        public function offsetGet($offset): mixed
        {
            return null;
        }

        public function offsetExists($offset): bool
        {
            return false;
        }

        public function offsetSet($offset, $value): void
        {
            //
        }

        public function offsetUnset($offset): void
        {
            //
        }
    });

    $app->register(GraphServiceProvider::class);

    $db = $app->make('db');

    $extensions = (new ReflectionClass($db))->getProperty('extensions');
    $extensions->setAccessible(true);

    expect($extensions->getValue($db))->toHaveKey('neo4j')
        ->and($app->isBound('db.connector.neo4j'))->toBeTrue()
        ->and($app->make('db.connector.neo4j'))->toBeInstanceOf(Neo4jConnector::class);
});

test('neo4j connector builds bolt uri from config', function () {
    $connector = new Neo4jConnector;
    $method = new ReflectionMethod($connector, 'buildConnectionUri');
    $method->setAccessible(true);

    $uri = $method->invoke($connector, [
        'scheme' => 'bolt',
        'host' => 'graph.local',
        'port' => 7687,
        'database' => 'neo4j',
    ]);

    expect($uri)->toBe('bolt://graph.local:7687?database=neo4j');
});
