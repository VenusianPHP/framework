<?php

namespace Tests\Database;

use Voyager\Vessel\Vessel;
use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\Connectors\ConnectionFactory;
use InvalidArgumentException;
use Mockery as m;
use PDO;
use ReflectionProperty;

beforeEach(function () {
    $this->db = new DB;

    $this->db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $this->db->addConnection([
        'url' => 'sqlite:///:memory:',
    ], 'url');

    $this->db->addConnection([
        'driver' => 'sqlite',
        'read' => [
            'database' => ':memory:',
        ],
        'write' => [
            'database' => ':memory:',
        ],
    ], 'read_write');

    $this->db->setAsGlobal();
});

test('connection can be created', function () {
    expect($this->db->getConnection()->getPdo())->toBeInstanceOf(PDO::class);
    expect($this->db->getConnection()->getReadPdo())->toBeInstanceOf(PDO::class);
    expect($this->db->getConnection('read_write')->getPdo())->toBeInstanceOf(PDO::class);
    expect($this->db->getConnection('read_write')->getReadPdo())->toBeInstanceOf(PDO::class);
    expect($this->db->getConnection('url')->getPdo())->toBeInstanceOf(PDO::class);
    expect($this->db->getConnection('url')->getReadPdo())->toBeInstanceOf(PDO::class);
});

test('connection from url has proper config', function () {
    $this->db->addConnection([
        'url' => 'mysql://root:pass@db/local?strict=true',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => false,
        'engine' => null,
    ], 'url-config');

    expect($this->db->getConnection('url-config')->getConfig())->toEqual([
        'name' => 'url-config',
        'driver' => 'mysql',
        'database' => 'local',
        'host' => 'db',
        'username' => 'root',
        'password' => 'pass',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ]);
});

test('single connection not created until needed', function () {
    $connection = $this->db->getConnection();
    $pdo = new ReflectionProperty(get_class($connection), 'pdo');
    $readPdo = new ReflectionProperty(get_class($connection), 'readPdo');

    expect($pdo->getValue($connection))->not->toBeInstanceOf(PDO::class);
    expect($readPdo->getValue($connection))->not->toBeInstanceOf(PDO::class);
});

test('read write connections not created until needed', function () {
    $connection = $this->db->getConnection('read_write');
    $pdo = new ReflectionProperty(get_class($connection), 'pdo');
    $readPdo = new ReflectionProperty(get_class($connection), 'readPdo');

    expect($pdo->getValue($connection))->not->toBeInstanceOf(PDO::class);
    expect($readPdo->getValue($connection))->not->toBeInstanceOf(PDO::class);
});

test('read write connection sets read pdo config', function () {
    $connection = $this->db->getConnection('read_write');

    $readPdoConfig = new ReflectionProperty(get_class($connection), 'readPdoConfig');

    $config = $readPdoConfig->getValue($connection);

    expect($config)->not->toBeEmpty();
    expect($config)->toHaveKey('database');
    expect($config['database'])->toBe(':memory:');
});

test('if driver isnt set exception is thrown', function () {
    $factory = new ConnectionFactory($container = m::mock(Vessel::class));
    $factory->createConnector(['foo']);
})->throws(InvalidArgumentException::class, 'A driver must be specified.');

test('exception is thrown on unsupported driver', function () {
    $factory = new ConnectionFactory($container = m::mock(Vessel::class));
    $container->shouldReceive('bound')->once()->andReturn(false);
    $factory->createConnector(['driver' => 'foo']);
})->throws(InvalidArgumentException::class, 'Unsupported driver [foo]');

test('custom connectors can be resolved via container', function () {
    $factory = new ConnectionFactory($container = m::mock(Vessel::class));
    $container->shouldReceive('bound')->once()->with('db.connector.foo')->andReturn(true);
    $container->shouldReceive('make')->once()->with('db.connector.foo')->andReturn('connector');

    expect($factory->createConnector(['driver' => 'foo']))->toBe('connector');
});

test('sqlite foreign key constraints', function () {
    $this->db->addConnection([
        'url' => 'sqlite:///:memory:?foreign_key_constraints=true',
    ], 'constraints_set');

    expect($this->db->getConnection()->select('PRAGMA foreign_keys')[0]->foreign_keys)->toEqual(0);

    expect($this->db->getConnection('constraints_set')->select('PRAGMA foreign_keys')[0]->foreign_keys)->toEqual(1);
});

test('sqlite busy timeout', function () {
    $this->db->addConnection([
        'url' => 'sqlite:///:memory:?busy_timeout=1234',
    ], 'busy_timeout_set');

    // Can't compare to 0, default value may be something else
    expect($this->db->getConnection()->select('PRAGMA busy_timeout')[0]->timeout)->not->toBe(1234);

    expect($this->db->getConnection('busy_timeout_set')->select('PRAGMA busy_timeout')[0]->timeout)->toBe(1234);
});

test('sqlite synchronous', function () {
    $this->db->addConnection([
        'url' => 'sqlite:///:memory:?synchronous=NORMAL',
    ], 'synchronous_set');

    expect($this->db->getConnection()->select('PRAGMA synchronous')[0]->synchronous)->toBe(2);

    expect($this->db->getConnection('synchronous_set')->select('PRAGMA synchronous')[0]->synchronous)->toBe(1);
});
