<?php

namespace Tests\Database;

use Voyager\Database\Connection;
use Voyager\Database\Schema\Grammars\Grammar;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseAbstractSchemaGrammarTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testCreateDatabase()
    {
        $connection = m::mock(Connection::class);
        $grammar = new class($connection) extends Grammar {
        };

        $this->assertSame('create database "foo"', $grammar->compileCreateDatabase('foo'));
    }

    public function testDropDatabaseIfExists()
    {
        $connection = m::mock(Connection::class);
        $grammar = new class($connection) extends Grammar {
        };

        $this->assertSame('drop database if exists "foo"', $grammar->compileDropDatabaseIfExists('foo'));
    }
}
