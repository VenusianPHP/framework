<?php

use Voyager\Vessel\Vessel;
use Voyager\Database\Connection;
use Voyager\Database\Schema\SQLiteBuilder;
use Voyager\Filesystem\Filesystem;
use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\MagicAliases\File;
use Mockery as m;

beforeEach(function () {
    $app = new Vessel;

    Vessel::setInstance($app)
        ->singleton('files', Filesystem::class);

    MagicAlias::setMagicAliasApplication($app);
});

afterEach(function () {
    Vessel::setInstance(null);
    MagicAlias::setMagicAliasApplication(null);
});

test('create database', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getSchemaGrammar')->once();

    $builder = new SQLiteBuilder($connection);

    File::shouldReceive('put')
        ->once()
        ->with('my_temporary_database_a', '')
        ->andReturn(20); // bytes

    expect($builder->createDatabase('my_temporary_database_a'))->toBeTrue();

    File::shouldReceive('put')
        ->once()
        ->with('my_temporary_database_b', '')
        ->andReturn(false);

    expect($builder->createDatabase('my_temporary_database_b'))->toBeFalse();
});

test('drop database if exists', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getSchemaGrammar')->once();

    $builder = new SQLiteBuilder($connection);

    File::shouldReceive('exists')
        ->once()
        ->andReturn(true);

    File::shouldReceive('delete')
        ->once()
        ->with('my_temporary_database_b')
        ->andReturn(true);

    expect($builder->dropDatabaseIfExists('my_temporary_database_b'))->toBeTrue();

    File::shouldReceive('exists')
        ->once()
        ->andReturn(false);

    expect($builder->dropDatabaseIfExists('my_temporary_database_c'))->toBeTrue();

    File::shouldReceive('exists')
        ->once()
        ->andReturn(true);

    File::shouldReceive('delete')
        ->once()
        ->with('my_temporary_database_c')
        ->andReturn(false);

    expect($builder->dropDatabaseIfExists('my_temporary_database_c'))->toBeFalse();
});
