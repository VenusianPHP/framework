<?php

use Voyager\Database\MySqlConnection;
use Voyager\Database\Schema\MySqlSchemaState;
use Pdo\Mysql;
use Symfony\Component\Process\Process;

test('connection string', function (string $expectedConnectionString, array $expectedVariables, array $dbConfig) {
    $connection = $this->createMock(MySqlConnection::class);
    $connection->method('getConfig')->willReturn($dbConfig);

    $schemaState = new MySqlSchemaState($connection);

    $versionInfo = ['version' => '8.0.0', 'isMariaDb' => false];

    // test connectionString
    $method = new ReflectionMethod(get_class($schemaState), 'connectionString');
    $connString = $method->invoke($schemaState, $versionInfo);

    self::assertEquals($expectedConnectionString, $connString);

    // test baseVariables
    $method = new ReflectionMethod(get_class($schemaState), 'baseVariables');
    $variables = $method->invoke($schemaState, $dbConfig);

    self::assertEquals($expectedVariables, $variables);
})->with([
    'default' => [
        ' --user="${:LARAVEL_LOAD_USER}" --password="${:LARAVEL_LOAD_PASSWORD}" --host="${:LARAVEL_LOAD_HOST}" --port="${:LARAVEL_LOAD_PORT}"', [
            'LARAVEL_LOAD_SOCKET' => '',
            'LARAVEL_LOAD_HOST' => '127.0.0.1',
            'LARAVEL_LOAD_PORT' => '',
            'LARAVEL_LOAD_USER' => 'root',
            'LARAVEL_LOAD_PASSWORD' => '',
            'LARAVEL_LOAD_DATABASE' => 'forge',
            'LARAVEL_LOAD_SSL_CA' => '',
            'LARAVEL_LOAD_SSL_CERT' => '',
            'LARAVEL_LOAD_SSL_KEY' => '',
        ], [
            'username' => 'root',
            'host' => '127.0.0.1',
            'database' => 'forge',
        ],
    ],
    'ssl_ca' => [
        ' --user="${:LARAVEL_LOAD_USER}" --password="${:LARAVEL_LOAD_PASSWORD}" --host="${:LARAVEL_LOAD_HOST}" --port="${:LARAVEL_LOAD_PORT}" --ssl-ca="${:LARAVEL_LOAD_SSL_CA}"', [
            'LARAVEL_LOAD_SOCKET' => '',
            'LARAVEL_LOAD_HOST' => '',
            'LARAVEL_LOAD_PORT' => '',
            'LARAVEL_LOAD_USER' => 'root',
            'LARAVEL_LOAD_PASSWORD' => '',
            'LARAVEL_LOAD_DATABASE' => 'forge',
            'LARAVEL_LOAD_SSL_CA' => 'ssl.ca',
            'LARAVEL_LOAD_SSL_CERT' => '',
            'LARAVEL_LOAD_SSL_KEY' => '',
        ], [
            'username' => 'root',
            'database' => 'forge',
            'options' => [
                Mysql::ATTR_SSL_CA => 'ssl.ca',
            ],
        ],
    ],
    'ssl_cert_and_key' => [
        ' --user="${:LARAVEL_LOAD_USER}" --password="${:LARAVEL_LOAD_PASSWORD}" --host="${:LARAVEL_LOAD_HOST}" --port="${:LARAVEL_LOAD_PORT}" --ssl-ca="${:LARAVEL_LOAD_SSL_CA}" --ssl-cert="${:LARAVEL_LOAD_SSL_CERT}" --ssl-key="${:LARAVEL_LOAD_SSL_KEY}"', [
            'LARAVEL_LOAD_SOCKET' => '',
            'LARAVEL_LOAD_HOST' => '',
            'LARAVEL_LOAD_PORT' => '',
            'LARAVEL_LOAD_USER' => 'root',
            'LARAVEL_LOAD_PASSWORD' => '',
            'LARAVEL_LOAD_DATABASE' => 'forge',
            'LARAVEL_LOAD_SSL_CA' => 'ssl.ca',
            'LARAVEL_LOAD_SSL_CERT' => '/path/to/client-cert.pem',
            'LARAVEL_LOAD_SSL_KEY' => '/path/to/client-key.pem',
        ], [
            'username' => 'root',
            'database' => 'forge',
            'options' => [
                Mysql::ATTR_SSL_CA => 'ssl.ca',
                Mysql::ATTR_SSL_CERT => '/path/to/client-cert.pem',
                Mysql::ATTR_SSL_KEY => '/path/to/client-key.pem',
            ],
        ],
    ],
    'no_ssl' => [
        ' --user="${:LARAVEL_LOAD_USER}" --password="${:LARAVEL_LOAD_PASSWORD}" --host="${:LARAVEL_LOAD_HOST}" --port="${:LARAVEL_LOAD_PORT}" --ssl-mode=DISABLED', [
            'LARAVEL_LOAD_SOCKET' => '',
            'LARAVEL_LOAD_HOST' => '',
            'LARAVEL_LOAD_PORT' => '',
            'LARAVEL_LOAD_USER' => 'root',
            'LARAVEL_LOAD_PASSWORD' => '',
            'LARAVEL_LOAD_DATABASE' => 'forge',
            'LARAVEL_LOAD_SSL_CA' => '',
            'LARAVEL_LOAD_SSL_CERT' => '',
            'LARAVEL_LOAD_SSL_KEY' => '',
        ], [
            'username' => 'root',
            'database' => 'forge',
            'options' => [
                Mysql::ATTR_SSL_VERIFY_SERVER_CERT => false,
            ],
        ],
    ],
    'unix socket' => [
        ' --user="${:LARAVEL_LOAD_USER}" --password="${:LARAVEL_LOAD_PASSWORD}" --socket="${:LARAVEL_LOAD_SOCKET}"', [
            'LARAVEL_LOAD_SOCKET' => '/tmp/mysql.sock',
            'LARAVEL_LOAD_HOST' => '',
            'LARAVEL_LOAD_PORT' => '',
            'LARAVEL_LOAD_USER' => 'root',
            'LARAVEL_LOAD_PASSWORD' => '',
            'LARAVEL_LOAD_DATABASE' => 'forge',
            'LARAVEL_LOAD_SSL_CA' => '',
            'LARAVEL_LOAD_SSL_CERT' => '',
            'LARAVEL_LOAD_SSL_KEY' => '',
        ], [
            'username' => 'root',
            'database' => 'forge',
            'unix_socket' => '/tmp/mysql.sock',
        ],
    ],
]);

test('execute dump process for depth', function () {
    $mockProcess = $this->createMock(Process::class);
    $mockProcess->method('setTimeout')->willReturnSelf();
    $mockProcess->method('mustRun')->will(
        $this->throwException(new Exception('column-statistics'))
    );

    $mockOutput = $this->createMock(stdClass::class);
    $mockVariables = [];

    $schemaState = $this->getMockBuilder(MySqlSchemaState::class)
        ->disableOriginalConstructor()
        ->onlyMethods(['makeProcess'])
        ->getMock();

    $schemaState->method('makeProcess')->willReturn($mockProcess);

    // test executeDumpProcess
    $method = new ReflectionMethod(get_class($schemaState), 'executeDumpProcess');
    $method->invoke($schemaState, $mockProcess, $mockOutput, $mockVariables, 31);
})->throws(Exception::class, 'Dump execution exceeded maximum depth of 30.');
