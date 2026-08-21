<?php

use Orchestra\Testbench\Concerns\CreatesApplication;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\System\Stubs\CustomProductStub;
use Tests\System\Stubs\InteractsWithMockedDatabase;
use Tests\System\Stubs\ProductStub;
use Voyager\Database\Connection;
use Voyager\Database\Query\Builder;
use Voyager\MagicAliases\DB;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\System\Testing\TestCase as TestingTestCase;

uses(InteractsWithMockedDatabase::class);

/** The table every assertion in this file runs against. */
const PRODUCTS_TABLE = 'products';

/**
 * A query builder double wired onto the test case's connection, answering
 * `exists` and `count` as asked.
 */
function mockCountBuilder($testCase, $existsResult, $deletedAtColumn = 'deleted_at', $countResult = null)
{
    $builder = Mockery::mock(Builder::class);

    $countResult = Arr::wrap($countResult);
    $countResult = ! empty($countResult) ? $countResult : [$existsResult ? 1 : 0];

    $key = array_key_first($testCase->data);
    $value = $testCase->data[$key];

    $builder->shouldReceive('where')->with($key, $value)->andReturnSelf();

    $builder->shouldReceive('select')->with(array_keys($testCase->data))->andReturnSelf();

    $builder->shouldReceive('limit')->andReturnSelf();

    $builder->shouldReceive('where')->with($testCase->data)->andReturnSelf();

    $builder->shouldReceive('whereNotNull')->with($deletedAtColumn)->andReturnSelf();

    $builder->shouldReceive('whereNull')->with($deletedAtColumn)->andReturnSelf();

    $builder->shouldReceive('exists')->andReturn($existsResult)->byDefault();

    $builder->shouldReceive('count')->andReturn(...$countResult)->byDefault();

    $testCase->connection->shouldReceive('table')
        ->with($testCase->table)
        ->andReturn($builder);

    return $builder;
}

beforeEach(function () {
    $this->table = PRODUCTS_TABLE;

    $this->data = [
        'title' => 'Spark',
        'name' => 'Laravel',
    ];

    $this->connection = Mockery::mock(Connection::class);
});

describe('assertDatabaseHas', function () {
    test('it passes when a row is found', function () {
        mockCountBuilder($this, true);

        $this->assertDatabaseHas($this->table, $this->data);
    });

    test('it accepts a model class', function () {
        mockCountBuilder($this, true);

        $this->assertDatabaseHas(ProductStub::class, $this->data);
    });

    test('a model instance constrains the query to that model', function () {
        $data = $this->data;

        $this->data = [
            'id' => 1,
            ...$this->data,
        ];

        mockCountBuilder($this, true);

        $this->assertDatabaseHas(new ProductStub(['id' => 1]), $data);
    });

    test('it reports an empty table', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The table is empty.');

        $builder = mockCountBuilder($this, false);

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertDatabaseHas($this->table, $this->data);
    });

    test('it reports similar rows', function () {
        $this->expectException(ExpectationFailedException::class);

        $this->expectExceptionMessage('Found similar results: '.json_encode([['title' => 'Forge']], JSON_PRETTY_PRINT));

        $builder = mockCountBuilder($this, false);

        $builder->shouldReceive('limit')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([['title' => 'Forge']]));

        $this->assertDatabaseHas($this->table, $this->data);
    });

    test('it truncates a long list of similar rows', function () {
        $this->expectException(ExpectationFailedException::class);

        $this->expectExceptionMessage('Found similar results: '.json_encode(['data', 'data', 'data'], JSON_PRETTY_PRINT).' and 2 others.');

        $builder = mockCountBuilder($this, false, countResult: [5, 5]);

        $builder->shouldReceive('limit')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(
            collect(array_fill(0, 3, 'data'))
        );

        $this->assertDatabaseHas($this->table, $this->data);
    });
});

describe('assertDatabaseMissing', function () {
    test('it passes when no row is found', function () {
        mockCountBuilder($this, false);

        $this->assertDatabaseMissing($this->table, $this->data);
    });

    test('it accepts a model class', function () {
        mockCountBuilder($this, false);

        $this->assertDatabaseMissing(ProductStub::class, $this->data);
    });

    test('a model instance constrains the query to that model', function () {
        $data = $this->data;

        $this->data = [
            'id' => 1,
            ...$this->data,
        ];

        mockCountBuilder($this, false);

        $this->assertDatabaseMissing(new ProductStub(['id' => 1]), $data);
    });

    test('it fails when a row is found', function () {
        $this->expectException(ExpectationFailedException::class);

        $builder = mockCountBuilder($this, true);

        $builder->shouldReceive('limit')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([$this->data]));

        $this->assertDatabaseMissing($this->table, $this->data);
    });

    test('it passes again when nothing is found', function () {
        mockCountBuilder($this, false);

        $this->assertDatabaseMissing($this->table, $this->data);
    });

    test('it fails again when something is found', function () {
        $this->expectException(ExpectationFailedException::class);

        $builder = mockCountBuilder($this, true);

        $builder->shouldReceive('get')->andReturn(collect([$this->data]));

        $this->assertDatabaseMissing($this->table, $this->data);
    });
});

describe('counting', function () {
    test('assertDatabaseCount matches the row count', function () {
        mockCountBuilder($this, true);

        $this->assertDatabaseCount($this->table, 1);
    });

    test('assertDatabaseCount accepts models', function () {
        mockCountBuilder($this, true);

        $this->assertDatabaseCount(ProductStub::class, 1);
        $this->assertDatabaseCount(new ProductStub, 1);
    });

    test('assertDatabaseEmpty accepts models', function () {
        mockCountBuilder($this, false);

        $this->assertDatabaseEmpty(ProductStub::class);
        $this->assertDatabaseEmpty(new ProductStub);
    });

    test('assertDatabaseCount reports the count it found', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that table [products] matches expected entries count of 3. Entries found: 1.');

        mockCountBuilder($this, true);

        $this->assertDatabaseCount($this->table, 3);
    });
});

describe('models', function () {
    test('assertModelMissing passes when the model is not found', function () {
        $this->data = ['id' => 1];

        $builder = mockCountBuilder($this, false);

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertModelMissing(new ProductStub($this->data));
    });

    test('assertModelExists passes when the model is found', function () {
        $this->data = ['id' => 1];

        $builder = mockCountBuilder($this, true);

        $builder->shouldReceive('get')->andReturn(collect($this->data));

        $this->assertModelExists(new ProductStub($this->data));
    });
});

describe('assertSoftDeleted', function () {
    test('it passes when a trashed row is found', function () {
        mockCountBuilder($this, true);

        $this->assertSoftDeleted($this->table, $this->data);
    });

    test('it accepts a model class string', function () {
        mockCountBuilder($this, true);

        $this->assertSoftDeleted(ProductStub::class, $this->data);
    });

    test('it reports an empty table', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The table is empty.');

        $builder = mockCountBuilder($this, false);

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertSoftDeleted($this->table, $this->data);
    });

    test('it reports an empty table for a model instance', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The table is empty.');

        $this->data = ['id' => 1];

        $builder = mockCountBuilder($this, false);

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertSoftDeleted(new ProductStub($this->data));
    });

    test('it honours a custom deleted at column on a model instance', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The table is empty.');

        $model = new CustomProductStub(['id' => 1, 'name' => 'Laravel']);
        $this->data = ['id' => 1, 'name' => 'Tailwind'];

        $builder = mockCountBuilder($this, false, 'trashed_at');

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertSoftDeleted($model, ['name' => 'Tailwind']);
    });

    test('it honours a custom deleted at column on a model class string', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The table is empty.');

        $model = new CustomProductStub(['id' => 1, 'name' => 'Laravel']);
        $this->data = ['id' => 1];

        $builder = mockCountBuilder($this, false, 'trashed_at');

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertSoftDeleted(CustomProductStub::class, ['id' => $model->id]);
    });
});

describe('assertNotSoftDeleted', function () {
    test('it passes when a live row is found', function () {
        mockCountBuilder($this, true);

        $this->assertNotSoftDeleted($this->table, $this->data);
    });

    test('it accepts a model class string', function () {
        mockCountBuilder($this, true);

        $this->assertNotSoftDeleted(ProductStub::class, $this->data);
    });

    test('it only counts matching models', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('Failed asserting that any existing row');

        $builder = mockCountBuilder($this, false);

        $builder->shouldReceive('get')->andReturn(collect(), collect(1));

        $this->assertNotSoftDeleted(ProductStub::class, $this->data);
    });

    test('it reports an empty table', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The table is empty.');

        $builder = mockCountBuilder($this, false);

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertNotSoftDeleted($this->table, $this->data);
    });

    test('it reports an empty table for a model instance', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The table is empty.');

        $this->data = ['id' => 1];

        $builder = mockCountBuilder($this, false);

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertNotSoftDeleted(new ProductStub($this->data));
    });

    test('it honours a custom deleted at column on a model instance', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The table is empty.');

        $model = new CustomProductStub(['id' => 1, 'name' => 'Laravel']);
        $this->data = ['id' => 1, 'name' => 'Tailwind'];

        $builder = mockCountBuilder($this, false, 'trashed_at');

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertNotSoftDeleted($model, ['name' => 'Tailwind']);
    });

    test('it honours a custom deleted at column on a model class string', function () {
        $this->expectException(ExpectationFailedException::class);
        $this->expectExceptionMessage('The table is empty.');

        $model = new CustomProductStub(['id' => 1, 'name' => 'Laravel']);
        $this->data = ['id' => 1];

        $builder = mockCountBuilder($this, false, 'trashed_at');

        $builder->shouldReceive('get')->andReturn(collect());

        $this->assertNotSoftDeleted(CustomProductStub::class, ['id' => $model->id]);
    });
});

describe('resolving names off a model', function () {
    test('the table name comes from the model', function () {
        expect($this->getTable(ProductStub::class))->toEqual($this->table)
            ->and($this->getTable(new ProductStub))->toEqual($this->table)
            ->and($this->getTable($this->table))->toEqual($this->table)
            ->and($this->getTable((new ProductStub)->setTable('all_products')))->toEqual('all_products');
    });

    test('the connection name comes from the model', function () {
        expect($this->getTableConnection(ProductStub::class))->toBe(null)
            ->and($this->getTableConnection(new ProductStub))->toBe(null)
            ->and($this->getTableConnection((new ProductStub)->setConnection('mysql')))->toBe('mysql');
    });

    test('the deleted at column comes from the model', function () {
        expect($this->getDeletedAtColumn(CustomProductStub::class))->toEqual('trashed_at')
            ->and($this->getDeletedAtColumn(new CustomProductStub))->toEqual('trashed_at');
    });
});

test('expectsDatabaseQueryCount asserts the query count per connection', function () {
    $case = new class('foo') extends TestingTestCase
    {
        use CreatesApplication;

        public function testExpectsDatabaseQueryCount()
        {
            $this->expectsDatabaseQueryCount(0);
        }
    };

    $case->setUp();
    $case->testExpectsDatabaseQueryCount();
    $case->tearDown();

    $case = new class('foo') extends TestingTestCase
    {
        use CreatesApplication;

        public function testExpectsDatabaseQueryCount()
        {
            $this->expectsDatabaseQueryCount(3);
        }
    };

    $case->setUp();
    $case->testExpectsDatabaseQueryCount();

    try {
        $case->tearDown();
        $this->fail();
    } catch (ExpectationFailedException $e) {
        expect($e->getMessage())->toBe("Expected 3 database queries on the [testing] connection. 0 occurred.\nFailed asserting that 0 is identical to 3.");
    }

    $case = new class('foo') extends TestingTestCase
    {
        use CreatesApplication;

        public function testExpectsDatabaseQueryCount()
        {
            $this->expectsDatabaseQueryCount(3);

            DB::pretend(function ($db) {
                $db->table('foo')->count();
                $db->table('foo')->count();
                $db->table('foo')->count();
                $db->table('foo')->count();
            });
        }
    };

    $case->setUp();
    $case->testExpectsDatabaseQueryCount();

    try {
        $case->tearDown();
        $this->fail();
    } catch (ExpectationFailedException $e) {
        expect($e->getMessage())->toBe("Expected 3 database queries on the [testing] connection. 4 occurred.\nFailed asserting that 4 is identical to 3.");
    }

    $case = new class('foo') extends TestingTestCase
    {
        use CreatesApplication;

        public function testExpectsDatabaseQueryCount()
        {
            $this->expectsDatabaseQueryCount(4);
            $this->expectsDatabaseQueryCount(1, 'mysql');

            DB::pretend(function ($db) {
                $db->table('foo')->count();
                $db->table('foo')->count();
                $db->table('foo')->count();
            });

            DB::connection('mysql')->pretend(function ($db) {
                $db->table('foo')->count();
            });
        }
    };

    $case->setUp();
    $case->testExpectsDatabaseQueryCount();
    $case->tearDown();
});
