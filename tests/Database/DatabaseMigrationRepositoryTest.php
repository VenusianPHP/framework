<?php

namespace Venusian\Tests\Database;

use Closure;
use Voyager\Database\Connection;
use Voyager\Database\ConnectionResolverInterface;
use Voyager\Database\Migrations\DatabaseMigrationRepository;
use Voyager\NutsAndBolts\Collection;
use Mockery as m;
use stdClass;

function dbMigRepoGetRepository()
{
    return new DatabaseMigrationRepository(m::mock(ConnectionResolverInterface::class), 'migrations');
}

test('get ran migrations list migrations by package', function () {
    $repo = dbMigRepoGetRepository();
    $query = m::mock(stdClass::class);
    $connectionMock = m::mock(Connection::class);
    $repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
    $repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
    $query->shouldReceive('orderBy')->once()->with('batch', 'asc')->andReturn($query);
    $query->shouldReceive('orderBy')->once()->with('migration', 'asc')->andReturn($query);
    $query->shouldReceive('pluck')->once()->with('migration')->andReturn(new Collection(['bar']));
    $query->shouldReceive('useWritePdo')->once()->andReturn($query);

    $this->assertEquals(['bar'], $repo->getRan());
});

test('get last migrations gets all migrations with the latest batch number', function () {
    $repo = $this->getMockBuilder(DatabaseMigrationRepository::class)->onlyMethods(['getLastBatchNumber'])->setConstructorArgs([
        $resolver = m::mock(ConnectionResolverInterface::class), 'migrations',
    ])->getMock();
    $repo->expects($this->once())->method('getLastBatchNumber')->willReturn(1);
    $query = m::mock(stdClass::class);
    $connectionMock = m::mock(Connection::class);
    $repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
    $repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
    $query->shouldReceive('where')->once()->with('batch', 1)->andReturn($query);
    $query->shouldReceive('orderBy')->once()->with('migration', 'desc')->andReturn($query);
    $query->shouldReceive('get')->once()->andReturn(new Collection(['foo']));
    $query->shouldReceive('useWritePdo')->once()->andReturn($query);

    $this->assertEquals(['foo'], $repo->getLast());
});

test('log method inserts record into migration table', function () {
    $repo = dbMigRepoGetRepository();
    $query = m::mock(stdClass::class);
    $connectionMock = m::mock(Connection::class);
    $repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
    $repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
    $query->shouldReceive('insert')->once()->with(['migration' => 'bar', 'batch' => 1]);
    $query->shouldReceive('useWritePdo')->once()->andReturn($query);

    $repo->log('bar', 1);
});

test('delete method removes a migration from the table', function () {
    $repo = dbMigRepoGetRepository();
    $query = m::mock(stdClass::class);
    $connectionMock = m::mock(Connection::class);
    $repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
    $repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
    $query->shouldReceive('where')->once()->with('migration', 'foo')->andReturn($query);
    $query->shouldReceive('delete')->once();
    $query->shouldReceive('useWritePdo')->once()->andReturn($query);
    $migration = (object) ['migration' => 'foo'];

    $repo->delete($migration);
});

test('get next batch number returns last batch number plus one', function () {
    $repo = $this->getMockBuilder(DatabaseMigrationRepository::class)->onlyMethods(['getLastBatchNumber'])->setConstructorArgs([
        m::mock(ConnectionResolverInterface::class), 'migrations',
    ])->getMock();
    $repo->expects($this->once())->method('getLastBatchNumber')->willReturn(1);

    $this->assertEquals(2, $repo->getNextBatchNumber());
});

test('get last batch number returns max batch', function () {
    $repo = dbMigRepoGetRepository();
    $query = m::mock(stdClass::class);
    $connectionMock = m::mock(Connection::class);
    $repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
    $repo->getConnection()->shouldReceive('table')->once()->with('migrations')->andReturn($query);
    $query->shouldReceive('max')->once()->andReturn(1);
    $query->shouldReceive('useWritePdo')->once()->andReturn($query);

    $this->assertEquals(1, $repo->getLastBatchNumber());
});

test('create repository creates proper database table', function () {
    $repo = dbMigRepoGetRepository();
    $schema = m::mock(stdClass::class);
    $connectionMock = m::mock(Connection::class);
    $repo->getConnectionResolver()->shouldReceive('connection')->with(null)->andReturn($connectionMock);
    $repo->getConnection()->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);
    $schema->shouldReceive('create')->once()->with('migrations', m::type(Closure::class));

    $repo->createRepository();
});

test('migrator runs without a dispatcher', function () {
    $db = new \Voyager\Database\Capsule\Manager;
    $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
    $db->setAsGlobal();
    $repository = new \Voyager\Database\Migrations\DatabaseMigrationRepository($db->getDatabaseManager(), 'migrations');
    $repository->createRepository();

    $migrator = new \Voyager\Database\Migrations\Migrator($repository, $db->getDatabaseManager(), new \Voyager\Filesystem\Filesystem);
    $dir = sys_get_temp_dir().'/vf-migrations-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/2026_01_01_000000_create_things.php', <<<'PHP'
    <?php
    use Voyager\Database\Migrations\Migration;
    use Voyager\Database\Schema\Blueprint;
    return new class extends Migration {
        public function up(): void { \Voyager\Database\Capsule\Manager::schema()->create('things', fn (Blueprint $t) => $t->id()); }
        public function down(): void {}
    };
    PHP);

    expect(fn () => $migrator->run([$dir]))->not->toThrow(\Throwable::class);
    expect($repository->getRan())->toBe(['2026_01_01_000000_create_things']);
    @unlink($dir.'/2026_01_01_000000_create_things.php'); @rmdir($dir);
});

