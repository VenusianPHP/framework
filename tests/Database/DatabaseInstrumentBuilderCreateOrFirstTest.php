<?php

namespace Tests\Database;

use Closure;
use Exception;
use Voyager\Database\Connection;
use Voyager\Database\ConnectionResolverInterface;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Query\Builder;
use Voyager\Database\UniqueConstraintViolationException;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mockery as m;
use PDO;

function dbCreateOrFirstMockConnectionForModel(Model $model, string $database, array $lastInsertIds = []): void
{
    $grammarClass = 'Voyager\\Database\\Query\\Grammars\\'.$database.'Grammar';
    $processorClass = 'Voyager\\Database\\Query\\Processors\\'.$database.'Processor';
    $processor = new $processorClass;
    $connection = m::mock(Connection::class, ['getPostProcessor' => $processor]);
    $grammar = new $grammarClass($connection);
    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('query')->andReturnUsing(function () use ($connection, $grammar, $processor) {
        return new Builder($connection, $grammar, $processor);
    });
    $connection->shouldReceive('getDatabaseName')->andReturn('database');
    $resolver = m::mock(ConnectionResolverInterface::class, ['connection' => $connection]);

    $class = get_class($model);
    $class::setConnectionResolver($resolver);

    $connection->shouldReceive('getPdo')->andReturn($pdo = m::mock(PDO::class));

    foreach ($lastInsertIds as $id) {
        $pdo->expects('lastInsertId')->andReturn($id);
    }
}

beforeEach(function () {
    Carbon::setTestNow('2023-01-01 00:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('create or first method creates new record', function (Closure|array $values) {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite', [123]);
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()->expects('insert')->with(
        'insert into "table" ("attr", "val", "updated_at", "created_at") values (?, ?, ?, ?)',
        ['foo', 'bar', '2023-01-01 00:00:00', '2023-01-01 00:00:00'],
    )->andReturnTrue();

    $result = $model->newQuery()->createOrFirst(['attr' => 'foo'], $values);

    expect($result->wasRecentlyCreated)->toBeTrue()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
})->with([
    'array' => [['val' => 'bar']],
    'closure' => [fn () => ['val' => 'bar']],
]);

test('create or first method retrieves existing record', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite');
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $sql = 'insert into "table" ("attr", "val", "updated_at", "created_at") values (?, ?, ?, ?)';
    $bindings = ['foo', 'bar', '2023-01-01 00:00:00', '2023-01-01 00:00:00'];

    $model->getConnection()
        ->expects('insert')
        ->with($sql, $bindings)
        ->andThrow(new UniqueConstraintViolationException('sqlite', $sql, $bindings, new Exception()));

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], false)
        ->andReturn([[
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]]);

    $result = $model->newQuery()->createOrFirst(['attr' => 'foo'], ['val' => 'bar']);

    expect($result->wasRecentlyCreated)->toBeFalse()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('first or create method retrieves existing record', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite');
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([[
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]]);

    $result = $model->newQuery()->firstOrCreate(['attr' => 'foo'], ['val' => 'bar']);

    expect($result->wasRecentlyCreated)->toBeFalse()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('first or create method creates new record', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite', [123]);
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([]);

    $model->getConnection()->expects('insert')->with(
        'insert into "table" ("attr", "val", "updated_at", "created_at") values (?, ?, ?, ?)',
        ['foo', 'bar', '2023-01-01 00:00:00', '2023-01-01 00:00:00'],
    )->andReturnTrue();

    $result = $model->newQuery()->firstOrCreate(['attr' => 'foo'], ['val' => 'bar']);

    expect($result->wasRecentlyCreated)->toBeTrue()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('first or create method retrieves record created just now', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite');
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([]);

    $sql = 'insert into "table" ("attr", "val", "updated_at", "created_at") values (?, ?, ?, ?)';
    $bindings = ['foo', 'bar', '2023-01-01 00:00:00', '2023-01-01 00:00:00'];

    $model->getConnection()
        ->expects('insert')
        ->with($sql, $bindings)
        ->andThrow(new UniqueConstraintViolationException('sqlite', $sql, $bindings, new Exception()));

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], false)
        ->andReturn([[
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]]);

    $result = $model->newQuery()->firstOrCreate(['attr' => 'foo'], ['val' => 'bar']);

    expect($result->wasRecentlyCreated)->toBeFalse()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('update or create method updates existing record', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite');
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([[
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]]);

    $model->getConnection()
        ->expects('update')
        ->with(
            'update "table" set "val" = ?, "updated_at" = ? where "id" = ?',
            ['baz', '2023-01-01 00:00:00', 123],
        )
        ->andReturn(1);

    $result = $model->newQuery()->updateOrCreate(['attr' => 'foo'], ['val' => 'baz']);

    expect($result->wasRecentlyCreated)->toBeFalse()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'val' => 'baz',
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('update or create method creates new record', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite', [123]);
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([]);

    $model->getConnection()->expects('insert')->with(
        'insert into "table" ("attr", "val", "updated_at", "created_at") values (?, ?, ?, ?)',
        ['foo', 'bar', '2023-01-01 00:00:00', '2023-01-01 00:00:00'],
    )->andReturnTrue();

    $result = $model->newQuery()->updateOrCreate(['attr' => 'foo'], ['val' => 'bar']);

    expect($result->wasRecentlyCreated)->toBeTrue()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('update or create method updates record created just now', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite');
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([]);

    $sql = 'insert into "table" ("attr", "val", "updated_at", "created_at") values (?, ?, ?, ?)';
    $bindings = ['foo', 'baz', '2023-01-01 00:00:00', '2023-01-01 00:00:00'];

    $model->getConnection()
        ->expects('insert')
        ->with($sql, $bindings)
        ->andThrow(new UniqueConstraintViolationException('sqlite', $sql, $bindings, new Exception()));

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], false)
        ->andReturn([[
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]]);

    $model->getConnection()
        ->expects('update')
        ->with(
            'update "table" set "val" = ?, "updated_at" = ? where "id" = ?',
            ['baz', '2023-01-01 00:00:00', 123],
        )
        ->andReturn(1);

    $result = $model->newQuery()->updateOrCreate(['attr' => 'foo'], ['val' => 'baz']);

    expect($result->wasRecentlyCreated)->toBeFalse()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'val' => 'baz',
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('increment or create method increments existing record', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite');
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([[
            'id' => 123,
            'attr' => 'foo',
            'count' => 1,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]]);

    $model->getConnection()
        ->expects('raw')
        ->with('"count" + 1')
        ->andReturn('2');

    $model->getConnection()
        ->expects('update')
        ->with(
            'update "table" set "count" = ?, "updated_at" = ? where "id" = ?',
            ['2', '2023-01-01 00:00:00', 123],
        )
        ->andReturn(1);

    $result = $model->newQuery()->incrementOrCreate(['attr' => 'foo'], 'count');

    expect($result->wasRecentlyCreated)->toBeFalse()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'count' => 2,
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('increment or create method creates new record', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite', [123]);
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([]);

    $model->getConnection()->expects('insert')->with(
        'insert into "table" ("attr", "count", "updated_at", "created_at") values (?, ?, ?, ?)',
        ['foo', '1', '2023-01-01 00:00:00', '2023-01-01 00:00:00'],
    )->andReturnTrue();

    $result = $model->newQuery()->incrementOrCreate(['attr' => 'foo']);

    expect($result->wasRecentlyCreated)->toBeTrue()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'count' => 1,
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('increment or create method increment parameters are passed', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite');
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([[
            'id' => 123,
            'attr' => 'foo',
            'val' => 'bar',
            'count' => 1,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]]);

    $model->getConnection()
        ->expects('raw')
        ->with('"count" + 2')
        ->andReturn('3');

    $model->getConnection()
        ->expects('update')
        ->with(
            'update "table" set "count" = ?, "val" = ?, "updated_at" = ? where "id" = ?',
            ['3', 'baz', '2023-01-01 00:00:00', 123],
        )
        ->andReturn(1);

    $result = $model->newQuery()->incrementOrCreate(['attr' => 'foo'], step: 2, extra: ['val' => 'baz']);

    expect($result->wasRecentlyCreated)->toBeFalse()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'count' => 3,
            'val' => 'baz',
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

test('increment or create method retrieves record created just now', function () {
    $model = new InstrumentBuilderCreateOrFirstTestModel();
    dbCreateOrFirstMockConnectionForModel($model, 'SQLite');
    $model->getConnection()->shouldReceive('transactionLevel')->andReturn(0);
    $model->getConnection()->shouldReceive('getName')->andReturn('sqlite');

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], true)
        ->andReturn([]);

    $sql = 'insert into "table" ("attr", "count", "updated_at", "created_at") values (?, ?, ?, ?)';
    $bindings = ['foo', '1', '2023-01-01 00:00:00', '2023-01-01 00:00:00'];

    $model->getConnection()
        ->expects('insert')
        ->with($sql, $bindings)
        ->andThrow(new UniqueConstraintViolationException('sqlite', $sql, $bindings, new Exception()));

    $model->getConnection()
        ->expects('select')
        ->with('select * from "table" where ("attr" = ?) limit 1', ['foo'], false)
        ->andReturn([[
            'id' => 123,
            'attr' => 'foo',
            'count' => 1,
            'created_at' => '2023-01-01 00:00:00',
            'updated_at' => '2023-01-01 00:00:00',
        ]]);

    $model->getConnection()
        ->expects('raw')
        ->with('"count" + 1')
        ->andReturn('2');

    $model->getConnection()
        ->expects('update')
        ->with(
            'update "table" set "count" = ?, "updated_at" = ? where "id" = ?',
            ['2', '2023-01-01 00:00:00', 123],
        )
        ->andReturn(1);

    $result = $model->newQuery()->incrementOrCreate(['attr' => 'foo']);

    expect($result->wasRecentlyCreated)->toBeFalse()
        ->and($result->toArray())->toEqual([
            'id' => 123,
            'attr' => 'foo',
            'count' => 2,
            'created_at' => '2023-01-01T00:00:00.000000Z',
            'updated_at' => '2023-01-01T00:00:00.000000Z',
        ]);
});

class InstrumentBuilderCreateOrFirstTestModel extends Model
{
    protected $table = 'table';
    protected $guarded = [];
}
