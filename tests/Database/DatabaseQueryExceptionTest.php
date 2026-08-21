<?php

use Voyager\Database\Connection;
use Voyager\Database\Query\Grammars\Grammar;
use Voyager\Database\QueryException;
use Voyager\NutsAndBolts\MagicAliases\DB;
use Mockery as m;

function databaseQueryExceptionConnection()
{
    $connection = m::mock(Connection::class);

    $grammar = new Grammar($connection);

    $connection->shouldReceive('getName')->andReturn('default');
    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('escape')->with(1, false)->andReturn(1);
    $connection->shouldReceive('escape')->with('br', false)->andReturn("'br'");

    return $connection;
}

test('if it embeds bindings into sql', function () {
    $connection = databaseQueryExceptionConnection();

    $sql = 'SELECT * FROM huehue WHERE a = ? and hue = ?';
    $bindings = [1, 'br'];

    $expectedSql = "SELECT * FROM huehue WHERE a = 1 and hue = 'br'";

    $pdoException = new PDOException('Mock SQL error');
    $exception = new QueryException($connection->getName(), $sql, $bindings, $pdoException);

    DB::shouldReceive('connection')->andReturn($connection);
    $result = $exception->getRawSql();

    $this->assertSame($expectedSql, $result);
});

test('if it returns same sql when there are no bindings', function () {
    $connection = databaseQueryExceptionConnection();

    $sql = "SELECT * FROM huehue WHERE a = 1 and hue = 'br'";
    $bindings = [];

    $expectedSql = $sql;

    $pdoException = new PDOException('Mock SQL error');
    $exception = new QueryException($connection->getName(), $sql, $bindings, $pdoException);

    DB::shouldReceive('connection')->andReturn($connection);
    $result = $exception->getRawSql();

    $this->assertSame($expectedSql, $result);
});

test('message includes connection info', function () {
    $pdoException = new PDOException('SQLSTATE[HY000] [2002] No such file or directory');
    $exception = new QueryException('mysql::read', 'SELECT * FROM users', [], $pdoException, [
        'driver' => 'mysql',
        'name' => 'mysql::read',
        'host' => '192.168.1.10',
        'port' => '3306',
        'database' => 'laravel_db',
        'unix_socket' => null,
    ]);

    $this->assertStringContainsString('Host: 192.168.1.10', $exception->getMessage());
    $this->assertStringContainsString('Port: 3306', $exception->getMessage());
    $this->assertStringContainsString('Database: laravel_db', $exception->getMessage());
    $this->assertStringContainsString('Connection: mysql::read', $exception->getMessage());
});

test('message includes unix socket', function () {
    $pdoException = new PDOException('SQLSTATE[HY000] [2002] No such file or directory');
    $exception = new QueryException('mysql', 'SELECT * FROM users', [], $pdoException, [
        'driver' => 'mysql',
        'unix_socket' => '/tmp/mysql.sock',
        'database' => 'laravel_db',
    ]);

    $this->assertStringContainsString('Socket: /tmp/mysql.sock', $exception->getMessage());
    $this->assertStringContainsString('Database: laravel_db', $exception->getMessage());
    $this->assertStringNotContainsString('Host:', $exception->getMessage());
});

test('message handles array hosts', function () {
    $pdoException = new PDOException('SQLSTATE[HY000] [2002] No such file or directory');
    $exception = new QueryException('mysql::read', 'SELECT * FROM users', [], $pdoException, [
        'driver' => 'mysql',
        'host' => ['192.168.1.10', '192.168.1.11'],
        'port' => '3306',
        'database' => 'laravel_db',
    ]);

    $this->assertStringContainsString('Host: 192.168.1.10, 192.168.1.11', $exception->getMessage());
});

test('message handles empty connection info', function () {
    $pdoException = new PDOException('SQLSTATE[HY000] [2002] No such file or directory');
    $exception = new QueryException('mysql', 'SELECT * FROM users', [], $pdoException, [
        'driver' => 'mysql',
        'host' => '',
        'port' => '',
        'database' => '',
    ]);

    $this->assertStringContainsString('Host: ,', $exception->getMessage());
    $this->assertStringContainsString('Database: ', $exception->getMessage());
});

test('message for sqlite only shows database', function () {
    $pdoException = new PDOException('SQLSTATE[HY000]: General error: 1 no such table');
    $exception = new QueryException('sqlite', 'SELECT * FROM users', [], $pdoException, [
        'driver' => 'sqlite',
        'name' => 'sqlite',
        'host' => null,
        'port' => null,
        'database' => '/path/to/database.sqlite',
        'unix_socket' => null,
    ]);

    $this->assertStringContainsString('Database: /path/to/database.sqlite', $exception->getMessage());
    $this->assertStringNotContainsString('Host:', $exception->getMessage());
    $this->assertStringNotContainsString('Port:', $exception->getMessage());
});

test('get connection info returns connection info', function () {
    $pdoException = new PDOException('Mock error');
    $connectionInfo = [
        'driver' => 'mysql',
        'name' => 'mysql::read',
        'host' => '192.168.1.10',
        'port' => '3306',
        'database' => 'laravel_db',
        'unix_socket' => null,
    ];
    $exception = new QueryException('mysql::read', 'SELECT * FROM users', [], $pdoException, $connectionInfo);

    $this->assertSame($connectionInfo, $exception->getConnectionDetails());
});

test('backward compatibility without connection info', function () {
    $pdoException = new PDOException('Mock SQL error');
    $exception = new QueryException('mysql', 'SELECT * FROM users WHERE id = ?', [1], $pdoException);

    $this->assertSame('Mock SQL error (Connection: mysql, SQL: SELECT * FROM users WHERE id = 1)', $exception->getMessage());
    $this->assertSame([], $exception->getConnectionDetails());
});
