<?php

namespace Tests\Database;

use Voyager\Database\Connection;
use Voyager\Database\Schema\Grammars\SqlServerGrammar;
use Voyager\Database\Schema\SqlServerBuilder;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SqlServerBuilderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testCreateDatabase()
    {
        $connection = m::mock(Connection::class);
        $grammar = new SqlServerGrammar($connection);

        $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
        $connection->shouldReceive('statement')->once()->with(
            'create database "my_temporary_database_a"'
        )->andReturn(true);

        $builder = new SqlServerBuilder($connection);
        $builder->createDatabase('my_temporary_database_a');
    }

    public function testDropDatabaseIfExists()
    {
        $connection = m::mock(Connection::class);
        $grammar = new SqlServerGrammar($connection);

        $connection->shouldReceive('getSchemaGrammar')->once()->andReturn($grammar);
        $connection->shouldReceive('statement')->once()->with(
            'drop database if exists "my_temporary_database_b"'
        )->andReturn(true);

        $builder = new SqlServerBuilder($connection);

        $builder->dropDatabaseIfExists('my_temporary_database_b');
    }
}
