<?php

namespace Tests\Database;

use Voyager\Vessel\Vessel;
use Voyager\Database\Connection;
use Voyager\Database\Schema\SQLiteBuilder;
use Voyager\Filesystem\Filesystem;
use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\MagicAliases\File;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DatabaseSQLiteBuilderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        $app = new Vessel;

        Vessel::setInstance($app)
            ->singleton('files', Filesystem::class);

        MagicAlias::setMagicAliasApplication($app);
    }

    protected function tearDown(): void
    {
        Vessel::setInstance(null);
        MagicAlias::setMagicAliasApplication(null);

        parent::tearDown();
    }

    public function testCreateDatabase()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('getSchemaGrammar')->once();

        $builder = new SQLiteBuilder($connection);

        File::shouldReceive('put')
            ->once()
            ->with('my_temporary_database_a', '')
            ->andReturn(20); // bytes

        $this->assertTrue($builder->createDatabase('my_temporary_database_a'));

        File::shouldReceive('put')
            ->once()
            ->with('my_temporary_database_b', '')
            ->andReturn(false);

        $this->assertFalse($builder->createDatabase('my_temporary_database_b'));
    }

    public function testDropDatabaseIfExists()
    {
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

        $this->assertTrue($builder->dropDatabaseIfExists('my_temporary_database_b'));

        File::shouldReceive('exists')
            ->once()
            ->andReturn(false);

        $this->assertTrue($builder->dropDatabaseIfExists('my_temporary_database_c'));

        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('delete')
            ->once()
            ->with('my_temporary_database_c')
            ->andReturn(false);

        $this->assertFalse($builder->dropDatabaseIfExists('my_temporary_database_c'));
    }
}
