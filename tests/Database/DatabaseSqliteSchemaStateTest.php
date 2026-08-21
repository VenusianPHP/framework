<?php

use Voyager\Database\Schema\SqliteSchemaState;
use Voyager\Database\SQLiteConnection;
use Voyager\Filesystem\Filesystem;
use Mockery as m;
use Symfony\Component\Process\Process;

test('load schema to database', function () {
    $config = ['driver' => 'sqlite', 'database' => 'database/database.sqlite', 'prefix' => '', 'foreign_key_constraints' => true, 'name' => 'sqlite'];
    $connection = m::mock(SQLiteConnection::class);
    $connection->shouldReceive('getConfig')->andReturn($config);
    $connection->shouldReceive('getDatabaseName')->andReturn($config['database']);

    $process = m::spy(Process::class);
    $processFactory = m::spy(function () use ($process) {
        return $process;
    });

    $schemaState = new SqliteSchemaState($connection, null, $processFactory);
    $schemaState->load('database/schema/sqlite-schema.dump');

    $processFactory->shouldHaveBeenCalled()->with('sqlite3 "${:LARAVEL_LOAD_DATABASE}" < "${:LARAVEL_LOAD_PATH}"');

    $process->shouldHaveReceived('mustRun')->with(null, [
        'LARAVEL_LOAD_DATABASE' => 'database/database.sqlite',
        'LARAVEL_LOAD_PATH' => 'database/schema/sqlite-schema.dump',
    ]);
});

test('load schema to in memory', function () {
    $config = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true, 'name' => 'sqlite'];
    $connection = m::mock(SQLiteConnection::class);
    $connection->shouldReceive('getConfig')->andReturn($config);
    $connection->shouldReceive('getDatabaseName')->andReturn($config['database']);
    $connection->shouldReceive('getPdo')->andReturn($pdo = m::spy(PDO::class));

    $files = m::mock(Filesystem::class);
    $files->shouldReceive('get')->andReturn('CREATE TABLE IF NOT EXISTS "migrations" ("id" integer not null primary key autoincrement, "migration" varchar not null, "batch" integer not null);');

    $schemaState = new SqliteSchemaState($connection, $files);
    $schemaState->load('database/schema/sqlite-schema.dump');

    $pdo->shouldHaveReceived('exec')->with('CREATE TABLE IF NOT EXISTS "migrations" ("id" integer not null primary key autoincrement, "migration" varchar not null, "batch" integer not null);');
});
