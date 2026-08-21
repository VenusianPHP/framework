<?php

use Voyager\Database\Connection;
use Voyager\Database\ConnectionInterface;
use Voyager\Database\ConnectionResolverInterface;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\Database\Instrument\RelationNotFoundException;
use Voyager\Database\Instrument\Relations\Relation;
use Voyager\Database\Instrument\SoftDeletes;
use Voyager\Database\Query\Builder as BaseBuilder;
use Voyager\Database\Query\Expression;
use Voyager\Database\Query\Grammars\Grammar;
use Voyager\Database\Query\Processors\Processor;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\Collection as BaseCollection;
use Mockery as m;

afterEach(function () {
    Carbon::setTestNow(null);
});

test('find method', function () {
    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $builder->setModel($model);
    $model->shouldReceive('getKeyType')->once()->andReturn('int');
    $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
    $builder->shouldReceive('first')->with(['column'])->andReturn('baz');

    $result = $builder->find('bar', ['column']);
    $this->assertSame('baz', $result);
});

test('find sole method', function () {
    $builder = m::mock(Builder::class.'[sole]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $builder->setModel($model);
    $model->shouldReceive('getKeyType')->once()->andReturn('int');
    $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
    $builder->shouldReceive('sole')->with(['column'])->andReturn('baz');

    $result = $builder->findSole('bar', ['column']);
    $this->assertSame('baz', $result);
});

test('find many method', function () {
    // ids are not empty
    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->andReturn('int');
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('foo_table.foo', ['one', 'two']);
    $builder->shouldReceive('get')->with(['column'])->andReturn(['baz']);

    $result = $builder->findMany(['one', 'two'], ['column']);
    $this->assertEquals(['baz'], $result);

    // ids are empty array
    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $model->shouldReceive('newCollection')->once()->withNoArgs()->andReturn('emptycollection');
    $model->shouldReceive('getKeyType')->andReturn('int');
    $builder->setModel($model);
    $builder->getQuery()->shouldNotReceive('whereIntegerInRaw');
    $builder->shouldNotReceive('get');

    $result = $builder->findMany([], ['column']);
    $this->assertSame('emptycollection', $result);

    // ids are empty collection
    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $model->shouldReceive('newCollection')->once()->withNoArgs()->andReturn('emptycollection');
    $builder->setModel($model);
    $builder->getQuery()->shouldNotReceive('whereIn');
    $builder->shouldNotReceive('get');

    $result = $builder->findMany(collect(), ['column']);
    $this->assertSame('emptycollection', $result);
});

test('find or new method model found', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->once()->andReturn('int');
    $model->shouldReceive('findOrNew')->once()->andReturn('baz');

    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
    $builder->shouldReceive('first')->with(['column'])->andReturn('baz');

    $expected = $model->findOrNew('bar', ['column']);
    $result = $builder->find('bar', ['column']);
    $this->assertEquals($expected, $result);
});

test('find or new method model not found', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->once()->andReturn('int');
    $model->shouldReceive('findOrNew')->once()->andReturn(m::mock(Model::class));

    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
    $builder->shouldReceive('first')->with(['column'])->andReturn(null);

    $result = $model->findOrNew('bar', ['column']);
    $findResult = $builder->find('bar', ['column']);
    $this->assertNull($findResult);
    $this->assertInstanceOf(Model::class, $result);
});

test('find or fail method throws model not found exception', function () {
    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->once()->andReturn('int');
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
    $builder->shouldReceive('first')->with(['column'])->andReturn(null);
    $builder->findOrFail('bar', ['column']);
})->throws(ModelNotFoundException::class);

test('find or fail method throws model not found exception with backed enum', function () {
    $exception = new ModelNotFoundException;
    $exception->setModel('Foo', InstrumentBuilderTestBackedEnum::Bar);

    $this->assertSame('No query results for model [Foo] bar', $exception->getMessage());
    $this->assertSame(['bar'], $exception->getIds());
});

test('find or fail method throws model not found exception with unit enum', function () {
    $exception = new ModelNotFoundException;
    $exception->setModel('Foo', InstrumentBuilderTestUnitEnum::Baz);

    $this->assertSame('No query results for model [Foo] Baz', $exception->getMessage());
    $this->assertSame(['Baz'], $exception->getIds());
});

test('find or fail method with many throws model not found exception', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKey')->andReturn(1);
    $model->shouldReceive('getKeyType')->andReturn('int');

    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('foo_table.foo', [1, 2]);
    $builder->shouldReceive('get')->with(['column'])->andReturn(new Collection([$model]));
    $builder->findOrFail([1, 2], ['column']);
})->throws(ModelNotFoundException::class);

test('find or fail method with many using collection throws model not found exception', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKey')->andReturn(1);
    $model->shouldReceive('getKeyType')->andReturn('int');

    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('foo_table.foo', [1, 2]);
    $builder->shouldReceive('get')->with(['column'])->andReturn(new Collection([$model]));
    $builder->findOrFail(new Collection([1, 2]), ['column']);
})->throws(ModelNotFoundException::class);

test('find or method', function () {
    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->andReturn('int');
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('where')->with('foo_table.foo', '=', 1)->twice();
    $builder->getQuery()->shouldReceive('where')->with('foo_table.foo', '=', 2)->once();
    $builder->shouldReceive('first')->andReturn($model)->once();
    $builder->shouldReceive('first')->with(['column'])->andReturn($model)->once();
    $builder->shouldReceive('first')->andReturn(null)->once();

    $this->assertSame($model, $builder->findOr(1, fn () => 'callback result'));
    $this->assertSame($model, $builder->findOr(1, ['column'], fn () => 'callback result'));
    $this->assertSame('callback result', $builder->findOr(2, fn () => 'callback result'));
});

test('find or method with many', function () {
    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $model1 = dbBuilderMockModel();
    $model2 = dbBuilderMockModel();
    $model1->shouldReceive('getKeyType')->andReturn('int');
    $model2->shouldReceive('getKeyType')->andReturn('int');
    $builder->setModel($model1);
    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->with('foo_table.foo', [1, 2])->twice();
    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->with('foo_table.foo', [1, 2, 3])->once();
    $builder->shouldReceive('get')->andReturn(new Collection([$model1, $model2]))->once();
    $builder->shouldReceive('get')->with(['column'])->andReturn(new Collection([$model1, $model2]))->once();
    $builder->shouldReceive('get')->andReturn(null)->once();

    $result = $builder->findOr([1, 2], fn () => 'callback result');
    $this->assertInstanceOf(Collection::class, $result);
    $this->assertSame($model1, $result[0]);
    $this->assertSame($model2, $result[1]);

    $result = $builder->findOr([1, 2], ['column'], fn () => 'callback result');
    $this->assertInstanceOf(Collection::class, $result);
    $this->assertSame($model1, $result[0]);
    $this->assertSame($model2, $result[1]);

    $result = $builder->findOr([1, 2, 3], fn () => 'callback result');
    $this->assertSame('callback result', $result);
});

test('find or method with many using collection', function () {
    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $model1 = dbBuilderMockModel();
    $model2 = dbBuilderMockModel();
    $model1->shouldReceive('getKeyType')->andReturn('int');
    $model2->shouldReceive('getKeyType')->andReturn('int');
    $builder->setModel($model1);
    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->with('foo_table.foo', [1, 2])->twice();
    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->with('foo_table.foo', [1, 2, 3])->once();
    $builder->shouldReceive('get')->andReturn(new Collection([$model1, $model2]))->once();
    $builder->shouldReceive('get')->with(['column'])->andReturn(new Collection([$model1, $model2]))->once();
    $builder->shouldReceive('get')->andReturn(null)->once();

    $result = $builder->findOr(new Collection([1, 2]), fn () => 'callback result');
    $this->assertInstanceOf(Collection::class, $result);
    $this->assertSame($model1, $result[0]);
    $this->assertSame($model2, $result[1]);

    $result = $builder->findOr(new Collection([1, 2]), ['column'], fn () => 'callback result');
    $this->assertInstanceOf(Collection::class, $result);
    $this->assertSame($model1, $result[0]);
    $this->assertSame($model2, $result[1]);

    $result = $builder->findOr(new Collection([1, 2, 3]), fn () => 'callback result');
    $this->assertSame('callback result', $result);
});

test('first or fail method throws model not found exception', function () {
    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $builder->setModel(dbBuilderMockModel());
    $builder->shouldReceive('first')->with(['column'])->andReturn(null);
    $builder->firstOrFail(['column']);
})->throws(ModelNotFoundException::class);

test('find with many', function () {
    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->andReturn('int');
    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('foo_table.foo', [1, 2]);
    $builder->setModel($model);
    $builder->shouldReceive('get')->with(['column'])->andReturn('baz');

    $result = $builder->find([1, 2], ['column']);
    $this->assertSame('baz', $result);
});

test('find with many using collection', function () {
    $ids = collect([1, 2]);
    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->andReturn('int');
    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('foo_table.foo', [1, 2]);
    $builder->setModel($model);
    $builder->shouldReceive('get')->with(['column'])->andReturn('baz');

    $result = $builder->find($ids, ['column']);
    $this->assertSame('baz', $result);
});

test('first method', function () {
    $builder = m::mock(Builder::class.'[get,take]', [dbBuilderMockQueryBuilder()]);
    $builder->shouldReceive('limit')->with(1)->andReturnSelf();
    $builder->shouldReceive('get')->with(['*'])->andReturn(new Collection(['bar']));

    $result = $builder->first();
    $this->assertSame('bar', $result);
});

test('qualify column', function () {
    $builder = new Builder(m::mock(BaseBuilder::class));
    $builder->shouldReceive('from')->with('foo_table');

    $builder->setModel(new InstrumentBuilderTestStubStringPrimaryKey);

    $this->assertSame('foo_table.column', $builder->qualifyColumn('column'));
});

test('qualify columns', function () {
    $builder = new Builder(m::mock(BaseBuilder::class));
    $builder->shouldReceive('from')->with('foo_table');

    $builder->setModel(new InstrumentBuilderTestStubStringPrimaryKey);

    $this->assertEquals(['foo_table.column', 'foo_table.name'], $builder->qualifyColumns(['column', 'name']));
});

test('get method loads models and hydrates eager relations', function () {
    $builder = m::mock(Builder::class.'[getModels,eagerLoadRelations]', [dbBuilderMockQueryBuilder()]);
    $builder->shouldReceive('applyScopes')->andReturnSelf();
    $builder->shouldReceive('getModels')->with(['foo'])->andReturn(['bar']);
    $builder->shouldReceive('eagerLoadRelations')->with(['bar'])->andReturn(['bar', 'baz']);
    $builder->setModel(dbBuilderMockModel());
    $builder->getModel()->shouldReceive('newCollection')->with(['bar', 'baz'])->andReturn(new Collection(['bar', 'baz']));

    $results = $builder->get(['foo']);
    $this->assertEquals(['bar', 'baz'], $results->all());
});

test('get method doesnt hydrate eager relations when no results are returned', function () {
    $builder = m::mock(Builder::class.'[getModels,eagerLoadRelations]', [dbBuilderMockQueryBuilder()]);
    $builder->shouldReceive('applyScopes')->andReturnSelf();
    $builder->shouldReceive('getModels')->with(['foo'])->andReturn([]);
    $builder->shouldReceive('eagerLoadRelations')->never();
    $builder->setModel(dbBuilderMockModel());
    $builder->getModel()->shouldReceive('newCollection')->with([])->andReturn(new Collection([]));

    $results = $builder->get(['foo']);
    $this->assertEquals([], $results->all());
});

test('value method with model found', function () {
    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $mockModel = new stdClass;
    $mockModel->name = 'foo';
    $builder->shouldReceive('first')->with(['name'])->andReturn($mockModel);

    $this->assertSame('foo', $builder->value('name'));
});

test('value method with model not found', function () {
    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $builder->shouldReceive('first')->with(['name'])->andReturn(null);

    $this->assertNull($builder->value('name'));
});

test('value or fail method with model found', function () {
    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $mockModel = new stdClass;
    $mockModel->name = 'foo';
    $builder->shouldReceive('first')->with(['name'])->andReturn($mockModel);

    $this->assertSame('foo', $builder->valueOrFail('name'));
});

test('value or fail method with model not found throws model not found exception', function () {
    $builder = m::mock(Builder::class.'[first]', [dbBuilderMockQueryBuilder()]);
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->once()->andReturn('int');
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('where')->once()->with('foo_table.foo', '=', 'bar');
    $builder->shouldReceive('first')->with(['column'])->andReturn(null);
    $builder->whereKey('bar')->valueOrFail('column');
})->throws(ModelNotFoundException::class);

test('chunk with last chunk complete', function () {
    $builder = m::mock(Builder::class.'[getOffset,getLimit,offset,limit,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = new Collection(['foo1', 'foo2']);
    $chunk2 = new Collection(['foo3', 'foo4']);
    $chunk3 = new Collection([]);

    $builder->shouldReceive('getOffset')->once()->andReturn(null);
    $builder->shouldReceive('getLimit')->once()->andReturn(null);
    $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
    $builder->shouldReceive('offset')->once()->with(2)->andReturnSelf();
    $builder->shouldReceive('offset')->once()->with(4)->andReturnSelf();
    $builder->shouldReceive('limit')->times(3)->with(2)->andReturnSelf();
    $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
    $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

    $builder->chunk(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    });
});

test('chunk with last chunk partial', function () {
    $builder = m::mock(Builder::class.'[getOffset,getLimit,offset,limit,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = new Collection(['foo1', 'foo2']);
    $chunk2 = new Collection(['foo3']);
    $builder->shouldReceive('getOffset')->once()->andReturn(null);
    $builder->shouldReceive('getLimit')->once()->andReturn(null);
    $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
    $builder->shouldReceive('offset')->once()->with(2)->andReturnSelf();
    $builder->shouldReceive('limit')->twice()->with(2)->andReturnSelf();
    $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

    $builder->chunk(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    });
});

test('chunk can be stopped by returning false', function () {
    $builder = m::mock(Builder::class.'[getOffset,getLimit,offset,limit,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = new Collection(['foo1', 'foo2']);
    $chunk2 = new Collection(['foo3']);

    $builder->shouldReceive('getOffset')->once()->andReturn(null);
    $builder->shouldReceive('getLimit')->once()->andReturn(null);
    $builder->shouldReceive('offset')->once()->with(0)->andReturnSelf();
    $builder->shouldReceive('limit')->once()->with(2)->andReturnSelf();
    $builder->shouldReceive('get')->times(1)->andReturn($chunk1);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk2);

    $builder->chunk(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);

        return false;
    });
});

test('chunk with count zero', function () {
    $builder = m::mock(Builder::class.'[getOffset,getLimit,offset,limit,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $builder->shouldReceive('getOffset')->once()->andReturn(null);
    $builder->shouldReceive('getLimit')->once()->andReturn(null);
    $builder->shouldReceive('offset')->never();
    $builder->shouldReceive('limit')->never();
    $builder->shouldReceive('get')->never();

    $builder->chunk(0, function () {
        $this->fail('Should not be called.');
    });
});

test('chunk paginates using id with last chunk complete', function () {
    $builder = m::mock(Builder::class.'[getOffset,getLimit,forPageAfterId,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = new Collection([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
    $chunk2 = new Collection([(object) ['someIdField' => 10], (object) ['someIdField' => 11]]);
    $chunk3 = new Collection([]);
    $builder->shouldReceive('getOffset')->andReturnNull();
    $builder->shouldReceive('getLimit')->andReturnNull();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 11, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);
    $callbackAssertor->shouldReceive('doSomething')->never()->with($chunk3);

    $builder->chunkById(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    }, 'someIdField');
});

test('chunk paginates using id with last chunk partial', function () {
    $builder = m::mock(Builder::class.'[getOffset,getLimit,forPageAfterId,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = new Collection([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
    $chunk2 = new Collection([(object) ['someIdField' => 10]]);
    $builder->shouldReceive('getOffset')->andReturnNull();
    $builder->shouldReceive('getLimit')->andReturnNull();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk1);
    $callbackAssertor->shouldReceive('doSomething')->once()->with($chunk2);

    $builder->chunkById(2, function ($results) use ($callbackAssertor) {
        $callbackAssertor->doSomething($results);
    }, 'someIdField');
});

test('chunk paginates using id with count zero', function () {
    $builder = m::mock(Builder::class.'[getOffset,getLimit,forPageAfterId,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $builder->shouldReceive('getOffset')->andReturnNull();
    $builder->shouldReceive('getLimit')->andReturnNull();
    $builder->shouldReceive('forPageAfterId')->never();
    $builder->shouldReceive('get')->never();

    $callbackAssertor = m::mock(stdClass::class);
    $callbackAssertor->shouldReceive('doSomething')->never();

    $builder->chunkById(0, function () {
        $this->fail('Should never be called.');
    }, 'someIdField');
});

test('lazy with last chunk complete', function () {
    $builder = m::mock(Builder::class.'[forPage,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
    $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
    $builder->shouldReceive('forPage')->once()->with(3, 2)->andReturnSelf();
    $builder->shouldReceive('get')->times(3)->andReturn(
        new Collection(['foo1', 'foo2']),
        new Collection(['foo3', 'foo4']),
        new Collection([])
    );

    $this->assertEquals(
        ['foo1', 'foo2', 'foo3', 'foo4'],
        $builder->lazy(2)->all()
    );
});

test('lazy with last chunk partial', function () {
    $builder = m::mock(Builder::class.'[forPage,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
    $builder->shouldReceive('forPage')->once()->with(2, 2)->andReturnSelf();
    $builder->shouldReceive('get')->times(2)->andReturn(
        new Collection(['foo1', 'foo2']),
        new Collection(['foo3'])
    );

    $this->assertEquals(
        ['foo1', 'foo2', 'foo3'],
        $builder->lazy(2)->all()
    );
});

test('lazy is lazy', function () {
    $builder = m::mock(Builder::class.'[forPage,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $builder->shouldReceive('forPage')->once()->with(1, 2)->andReturnSelf();
    $builder->shouldReceive('get')->once()->andReturn(new Collection(['foo1', 'foo2']));

    $this->assertEquals(['foo1', 'foo2'], $builder->lazy(2)->take(2)->all());
});

test('lazy by id with last chunk complete', function () {
    $builder = m::mock(Builder::class.'[forPageAfterId,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = new Collection([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
    $chunk2 = new Collection([(object) ['someIdField' => 10], (object) ['someIdField' => 11]]);
    $chunk3 = new Collection([]);
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 11, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('get')->times(3)->andReturn($chunk1, $chunk2, $chunk3);

    $this->assertEquals(
        [
            (object) ['someIdField' => 1],
            (object) ['someIdField' => 2],
            (object) ['someIdField' => 10],
            (object) ['someIdField' => 11],
        ],
        $builder->lazyById(2, 'someIdField')->all()
    );
});

test('lazy by id with last chunk partial', function () {
    $builder = m::mock(Builder::class.'[forPageAfterId,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = new Collection([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
    $chunk2 = new Collection([(object) ['someIdField' => 10]]);
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 2, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('get')->times(2)->andReturn($chunk1, $chunk2);

    $this->assertEquals(
        [
            (object) ['someIdField' => 1],
            (object) ['someIdField' => 2],
            (object) ['someIdField' => 10],
        ],
        $builder->lazyById(2, 'someIdField')->all()
    );
});

test('lazy by id is lazy', function () {
    $builder = m::mock(Builder::class.'[forPageAfterId,get]', [dbBuilderMockQueryBuilder()]);
    $builder->getQuery()->orders[] = ['column' => 'foobar', 'direction' => 'asc'];

    $chunk1 = new Collection([(object) ['someIdField' => 1], (object) ['someIdField' => 2]]);
    $builder->shouldReceive('forPageAfterId')->once()->with(2, 0, 'someIdField')->andReturnSelf();
    $builder->shouldReceive('get')->once()->andReturn($chunk1);

    $this->assertEquals(
        [
            (object) ['someIdField' => 1],
            (object) ['someIdField' => 2],
        ],
        $builder->lazyById(2, 'someIdField')->take(2)->all()
    );
});

test('pluck returns the mutated attributes of a model', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('pluck')->with('name', '')->andReturn(new BaseCollection(['bar', 'baz']));
    $builder->setModel(dbBuilderMockModel());
    $builder->getModel()->shouldReceive('hasAnyGetMutator')->with('name')->andReturn(true);
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'bar'])->andReturn(new InstrumentBuilderTestPluckStub(['name' => 'bar']));
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'baz'])->andReturn(new InstrumentBuilderTestPluckStub(['name' => 'baz']));

    $this->assertEquals(['foo_bar', 'foo_baz'], $builder->pluck('name')->all());
});

test('pluck returns the casted attributes of a model', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('pluck')->with('name', '')->andReturn(new BaseCollection(['bar', 'baz']));
    $builder->setModel(dbBuilderMockModel());
    $builder->getModel()->shouldReceive('hasAnyGetMutator')->with('name')->andReturn(false);
    $builder->getModel()->shouldReceive('hasCast')->with('name')->andReturn(true);
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'bar'])->andReturn(new InstrumentBuilderTestPluckStub(['name' => 'bar']));
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'baz'])->andReturn(new InstrumentBuilderTestPluckStub(['name' => 'baz']));

    $this->assertEquals(['foo_bar', 'foo_baz'], $builder->pluck('name')->all());
});

test('pluck returns the date attributes of a model', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('pluck')->with('created_at', '')->andReturn(new BaseCollection(['2010-01-01 00:00:00', '2011-01-01 00:00:00']));
    $builder->setModel(dbBuilderMockModel());
    $builder->getModel()->shouldReceive('hasAnyGetMutator')->with('created_at')->andReturn(false);
    $builder->getModel()->shouldReceive('hasCast')->with('created_at')->andReturn(false);
    $builder->getModel()->shouldReceive('getDates')->andReturn(['created_at']);
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['created_at' => '2010-01-01 00:00:00'])->andReturn(new InstrumentBuilderTestPluckDatesStub(['created_at' => '2010-01-01 00:00:00']));
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['created_at' => '2011-01-01 00:00:00'])->andReturn(new InstrumentBuilderTestPluckDatesStub(['created_at' => '2011-01-01 00:00:00']));

    $this->assertEquals(['date_2010-01-01 00:00:00', 'date_2011-01-01 00:00:00'], $builder->pluck('created_at')->all());
});

test('qualified pluck returns the mutated attributes of a model', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('qualifyColumn')->with('name')->andReturn('foo_table.name');

    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('pluck')->with($model->qualifyColumn('name'), '')->andReturn(new BaseCollection(['bar', 'baz']));
    $builder->setModel($model);
    $builder->getModel()->shouldReceive('hasAnyGetMutator')->with('name')->andReturn(true);
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'bar'])->andReturn(new InstrumentBuilderTestPluckStub(['name' => 'bar']));
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'baz'])->andReturn(new InstrumentBuilderTestPluckStub(['name' => 'baz']));

    $this->assertEquals(['foo_bar', 'foo_baz'], $builder->pluck($model->qualifyColumn('name'))->all());
});

test('qualified pluck returns the casted attributes of a model', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('qualifyColumn')->with('name')->andReturn('foo_table.name');

    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('pluck')->with($model->qualifyColumn('name'), '')->andReturn(new BaseCollection(['bar', 'baz']));
    $builder->setModel($model);
    $builder->getModel()->shouldReceive('hasAnyGetMutator')->with('name')->andReturn(false);
    $builder->getModel()->shouldReceive('hasCast')->with('name')->andReturn(true);
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'bar'])->andReturn(new InstrumentBuilderTestPluckStub(['name' => 'bar']));
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['name' => 'baz'])->andReturn(new InstrumentBuilderTestPluckStub(['name' => 'baz']));

    $this->assertEquals(['foo_bar', 'foo_baz'], $builder->pluck($model->qualifyColumn('name'))->all());
});

test('qualified pluck returns the date attributes of a model', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('qualifyColumn')->with('created_at')->andReturn('foo_table.created_at');

    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('pluck')->with($model->qualifyColumn('created_at'), '')->andReturn(new BaseCollection(['2010-01-01 00:00:00', '2011-01-01 00:00:00']));
    $builder->setModel($model);
    $builder->getModel()->shouldReceive('hasAnyGetMutator')->with('created_at')->andReturn(false);
    $builder->getModel()->shouldReceive('hasCast')->with('created_at')->andReturn(false);
    $builder->getModel()->shouldReceive('getDates')->andReturn(['created_at']);
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['created_at' => '2010-01-01 00:00:00'])->andReturn(new InstrumentBuilderTestPluckDatesStub(['created_at' => '2010-01-01 00:00:00']));
    $builder->getModel()->shouldReceive('newFromBuilder')->with(['created_at' => '2011-01-01 00:00:00'])->andReturn(new InstrumentBuilderTestPluckDatesStub(['created_at' => '2011-01-01 00:00:00']));

    $this->assertEquals(['date_2010-01-01 00:00:00', 'date_2011-01-01 00:00:00'], $builder->pluck($model->qualifyColumn('created_at'))->all());
});

test('pluck without model getter just returns the attributes found in database', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('pluck')->with('name', '')->andReturn(new BaseCollection(['bar', 'baz']));
    $builder->setModel(dbBuilderMockModel());
    $builder->getModel()->shouldReceive('hasAnyGetMutator')->with('name')->andReturn(false);
    $builder->getModel()->shouldReceive('hasCast')->with('name')->andReturn(false);
    $builder->getModel()->shouldReceive('getDates')->andReturn(['created_at']);

    $this->assertEquals(['bar', 'baz'], $builder->pluck('name')->all());
});

test('local macros are called on builder', function () {
    unset($_SERVER['__test.builder']);
    $builder = new Builder(new BaseBuilder(
        m::mock(ConnectionInterface::class),
        m::mock(Grammar::class),
        m::mock(Processor::class)
    ));
    $builder->macro('fooBar', function ($builder) {
        $_SERVER['__test.builder'] = $builder;

        return $builder;
    });
    $result = $builder->fooBar();

    $this->assertTrue($builder->hasMacro('fooBar'));
    $this->assertEquals($builder, $result);
    $this->assertEquals($builder, $_SERVER['__test.builder']);
    unset($_SERVER['__test.builder']);
});

test('global macros are called on builder', function () {
    Builder::macro('foo', function ($bar) {
        return $bar;
    });

    Builder::macro('bam', function () {
        return $this->getQuery();
    });

    $builder = dbBuilderGetBuilder();

    $this->assertTrue(Builder::hasGlobalMacro('foo'));
    $this->assertSame('bar', $builder->foo('bar'));
    $this->assertEquals($builder->bam(), $builder->getQuery());
});

test('missing static macros throws proper exception', function () {
    Builder::missingMacro();
})->throws(BadMethodCallException::class, 'Call to undefined method Voyager\Database\Instrument\Builder::missingMacro()');

test('get models properly hydrates models', function () {
    $builder = m::mock(Builder::class.'[get]', [dbBuilderMockQueryBuilder()]);
    $records[] = ['name' => 'taylor', 'age' => 26];
    $records[] = ['name' => 'dayle', 'age' => 28];
    $builder->getQuery()->shouldReceive('get')->once()->with(['foo'])->andReturn(new BaseCollection($records));
    $model = m::mock(Model::class.'[getTable,hydrate]');
    $model->shouldReceive('getTable')->once()->andReturn('foo_table');
    $builder->setModel($model);
    $model->shouldReceive('hydrate')->once()->with($records)->andReturn(new Collection(['hydrated']));
    $models = $builder->getModels(['foo']);

    $this->assertEquals(['hydrated'], $models);
});

test('eager load relations load top level relationships', function () {
    $builder = m::mock(Builder::class.'[eagerLoadRelation]', [dbBuilderMockQueryBuilder()]);
    $nop1 = function () {
        //
    };
    $nop2 = function () {
        //
    };
    $builder->setEagerLoads(['foo' => $nop1, 'foo.bar' => $nop2]);
    $builder->shouldAllowMockingProtectedMethods()->shouldReceive('eagerLoadRelation')->with(['models'], 'foo', $nop1)->andReturn(['foo']);

    $results = $builder->eagerLoadRelations(['models']);
    $this->assertEquals(['foo'], $results);
});

test('eager load relations can be flushed', function () {
    $builder = m::mock(Builder::class.'[eagerLoadRelation]', [dbBuilderMockQueryBuilder()]);

    $builder->setEagerLoads(['foo']);

    $this->assertSame(['foo'], $builder->getEagerLoads());

    $builder->withoutEagerLoads();

    $this->assertEmpty($builder->getEagerLoads());
});

test('relationship eager load process', function () {
    $builder = m::mock(Builder::class.'[getRelation]', [dbBuilderMockQueryBuilder()]);
    $builder->setEagerLoads(['orders' => function ($query) {
        $_SERVER['__instrument.constrain'] = $query;
    }]);
    $relation = m::mock(stdClass::class);
    $relation->shouldReceive('addEagerConstraints')->once()->with(['models']);
    $relation->shouldReceive('initRelation')->once()->with(['models'], 'orders')->andReturn(['models']);
    $relation->shouldReceive('getEager')->once()->andReturn(['results']);
    $relation->shouldReceive('match')->once()->with(['models'], ['results'], 'orders')->andReturn(['models.matched']);
    $builder->shouldReceive('getRelation')->once()->with('orders')->andReturn($relation);
    $results = $builder->eagerLoadRelations(['models']);

    $this->assertEquals(['models.matched'], $results);
    $this->assertEquals($relation, $_SERVER['__instrument.constrain']);
    unset($_SERVER['__instrument.constrain']);
});

test('relationship eager load process for implicitly empty', function () {
    $queryBuilder = dbBuilderMockQueryBuilder();
    $builder = m::mock(Builder::class.'[getRelation]', [$queryBuilder]);
    $builder->setEagerLoads(['parentFoo' => function ($query) {
        $_SERVER['__instrument.constrain'] = $query;
    }]);
    $model = new InstrumentBuilderTestModelSelfRelatedStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');

    $models = [
        new InstrumentBuilderTestModelSelfRelatedStub,
        new InstrumentBuilderTestModelSelfRelatedStub,
    ];
    $relation = m::mock($model->parentFoo());

    $builder->shouldReceive('getRelation')->once()->with('parentFoo')->andReturn($relation);

    $results = $builder->eagerLoadRelations($models);

    unset($_SERVER['__instrument.constrain']);
});

test('get relation properly sets nested relationships', function () {
    $builder = dbBuilderGetBuilder();
    $builder->setModel(dbBuilderMockModel());
    $builder->getModel()->shouldReceive('newInstance->orders')->once()->andReturn($relation = m::mock(stdClass::class));
    $relationQuery = m::mock(stdClass::class);
    $relation->shouldReceive('getQuery')->andReturn($relationQuery);
    $relationQuery->shouldReceive('with')->once()->with(['lines' => null, 'lines.details' => null]);
    $builder->setEagerLoads(['orders' => null, 'orders.lines' => null, 'orders.lines.details' => null]);

    $builder->getRelation('orders');
});

test('get relation properly sets nested relationships with similar names', function () {
    $builder = dbBuilderGetBuilder();
    $builder->setModel(dbBuilderMockModel());
    $builder->getModel()->shouldReceive('newInstance->orders')->once()->andReturn($relation = m::mock(stdClass::class));
    $builder->getModel()->shouldReceive('newInstance->ordersGroups')->once()->andReturn($groupsRelation = m::mock(stdClass::class));

    $relationQuery = m::mock(stdClass::class);
    $relation->shouldReceive('getQuery')->andReturn($relationQuery);

    $groupRelationQuery = m::mock(stdClass::class);
    $groupsRelation->shouldReceive('getQuery')->andReturn($groupRelationQuery);
    $groupRelationQuery->shouldReceive('with')->once()->with(['lines' => null, 'lines.details' => null]);

    $builder->setEagerLoads(['orders' => null, 'ordersGroups' => null, 'ordersGroups.lines' => null, 'ordersGroups.lines.details' => null]);

    $builder->getRelation('orders');
    $builder->getRelation('ordersGroups');
});

test('get relation throws exception', function () {
    $builder = dbBuilderGetBuilder();
    $model = dbBuilderMockModel();
    $model->shouldReceive('newInstance')->once()->andReturn(new class extends Model {});
    $builder->setModel($model);

    $builder->getRelation('invalid');
})->throws(RelationNotFoundException::class);

test('eager load parsing sets proper relationships', function () {
    $builder = dbBuilderGetBuilder();
    $builder->with(['orders', 'orders.lines']);
    $eagers = $builder->getEagerLoads();

    $this->assertEquals(['orders', 'orders.lines'], array_keys($eagers));
    $this->assertInstanceOf(Closure::class, $eagers['orders']);
    $this->assertInstanceOf(Closure::class, $eagers['orders.lines']);

    $builder = dbBuilderGetBuilder();
    $builder->with('orders', 'orders.lines');
    $eagers = $builder->getEagerLoads();

    $this->assertEquals(['orders', 'orders.lines'], array_keys($eagers));
    $this->assertInstanceOf(Closure::class, $eagers['orders']);
    $this->assertInstanceOf(Closure::class, $eagers['orders.lines']);

    $builder = dbBuilderGetBuilder();
    $builder->with(['orders.lines']);
    $eagers = $builder->getEagerLoads();

    $this->assertEquals(['orders', 'orders.lines'], array_keys($eagers));
    $this->assertInstanceOf(Closure::class, $eagers['orders']);
    $this->assertInstanceOf(Closure::class, $eagers['orders.lines']);

    $builder = dbBuilderGetBuilder();
    $builder->with(['orders' => function () {
        return 'foo';
    }]);
    $eagers = $builder->getEagerLoads();

    $this->assertSame('foo', $eagers['orders'](dbBuilderGetBuilder()));

    $builder = dbBuilderGetBuilder();
    $builder->with(['orders.lines' => function () {
        return 'foo';
    }]);
    $eagers = $builder->getEagerLoads();

    $this->assertInstanceOf(Closure::class, $eagers['orders']);
    $this->assertNull($eagers['orders']());
    $this->assertSame('foo', $eagers['orders.lines'](dbBuilderGetBuilder()));

    $builder = dbBuilderGetBuilder();
    $builder->with('orders.lines', function () {
        return 'foo';
    });
    $eagers = $builder->getEagerLoads();

    $this->assertInstanceOf(Closure::class, $eagers['orders']);
    $this->assertNull($eagers['orders']());
    $this->assertSame('foo', $eagers['orders.lines'](dbBuilderGetBuilder()));
});

test('query pass thru', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('foobar')->once()->andReturn('foo');

    $this->assertInstanceOf(Builder::class, $builder->foobar());

    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('insert')->once()->with(['bar'])->andReturn('foo');

    $this->assertSame('foo', $builder->insert(['bar']));

    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('insertOrIgnore')->once()->with(['bar'])->andReturn('foo');

    $this->assertSame('foo', $builder->insertOrIgnore(['bar']));

    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('insertOrIgnoreUsing')->once()->with(['bar'], 'baz')->andReturn('foo');

    $this->assertSame('foo', $builder->insertOrIgnoreUsing(['bar'], 'baz'));

    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('insertGetId')->once()->with(['bar'])->andReturn('foo');

    $this->assertSame('foo', $builder->insertGetId(['bar']));

    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('insertUsing')->once()->with(['bar'], 'baz')->andReturn('foo');

    $this->assertSame('foo', $builder->insertUsing(['bar'], 'baz'));

    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('raw')->once()->with('bar')->andReturn('foo');

    $this->assertSame('foo', $builder->raw('bar'));
});

test('query scopes', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('from');
    $builder->getQuery()->shouldReceive('where')->once()->with('foo', 'bar');
    $builder->setModel($model = new InstrumentBuilderTestScopeStub);
    $result = $builder->approved();

    $this->assertEquals($builder, $result);
});

test('query dynamic scopes', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('from');
    $builder->getQuery()->shouldReceive('where')->once()->with('bar', 'foo');
    $builder->setModel($model = new InstrumentBuilderTestDynamicScopeStub);
    $result = $builder->dynamic('bar', 'foo');

    $this->assertEquals($builder, $result);
});

test('query dynamic scopes named', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('from');
    $builder->getQuery()->shouldReceive('where')->once()->with('foo', 'foo');
    $builder->setModel($model = new InstrumentBuilderTestDynamicScopeStub);
    $result = $builder->dynamic(bar: 'foo');

    $this->assertEquals($builder, $result);
});

test('nested where', function () {
    $nestedQuery = m::mock(Builder::class);
    $nestedRawQuery = dbBuilderMockQueryBuilder();
    $nestedQuery->shouldReceive('getQuery')->once()->andReturn($nestedRawQuery);
    $nestedQuery->shouldReceive('getEagerLoads')->once()->andReturn([]);
    $model = dbBuilderMockModel()->makePartial();
    $model->shouldReceive('newQueryWithoutRelationships')->once()->andReturn($nestedQuery);
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('from');
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('addNestedWhereQuery')->once()->with($nestedRawQuery, 'and');
    $nestedQuery->shouldReceive('foo')->once();

    $result = $builder->where(function ($query) {
        $query->foo();
    });
    $this->assertEquals($builder, $result);
});

test('real nested where with scopes', function () {
    $model = new InstrumentBuilderTestNestedStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->where('foo', '=', 'bar')->where(function ($query) {
        $query->where('baz', '>', 9000);
    });
    $this->assertSame('select * from "table" where "foo" = ? and ("baz" > ?) and "table"."deleted_at" is null', $query->toSql());
    $this->assertEquals(['bar', 9000], $query->getBindings());
});

test('real nested where with scopes macro', function () {
    $model = new InstrumentBuilderTestNestedStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->where('foo', '=', 'bar')->where(function ($query) {
        $query->where('baz', '>', 9000)->onlyTrashed();
    })->withTrashed();
    $this->assertSame('select * from "table" where "foo" = ? and ("baz" > ? and "table"."deleted_at" is not null)', $query->toSql());
    $this->assertEquals(['bar', 9000], $query->getBindings());
});

test('real nested where with multiple scopes and one dead scope', function () {
    $model = new InstrumentBuilderTestNestedStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->empty()->where('foo', '=', 'bar')->empty()->where(function ($query) {
        $query->empty()->where('baz', '>', 9000);
    });
    $this->assertSame('select * from "table" where "foo" = ? and ("baz" > ?) and "table"."deleted_at" is null', $query->toSql());
    $this->assertEquals(['bar', 9000], $query->getBindings());
});

test('simple where not', function () {
    $model = new InstrumentBuilderTestStub();
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->whereNot('name', 'foo')->whereNot('name', '<>', 'bar');
    $this->assertEquals('select * from "table" where not "name" = ? and not "name" <> ?', $query->toSql());
    $this->assertEquals(['foo', 'bar'], $query->getBindings());
});

test('where not', function () {
    $nestedQuery = m::mock(Builder::class);
    $nestedRawQuery = dbBuilderMockQueryBuilder();
    $nestedQuery->shouldReceive('getQuery')->once()->andReturn($nestedRawQuery);
    $nestedQuery->shouldReceive('getEagerLoads')->once()->andReturn([]);
    $model = dbBuilderMockModel()->makePartial();
    $model->shouldReceive('newQueryWithoutRelationships')->once()->andReturn($nestedQuery);
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('from');
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('addNestedWhereQuery')->once()->with($nestedRawQuery, 'and not');
    $nestedQuery->shouldReceive('foo')->once();

    $result = $builder->whereNot(function ($query) {
        $query->foo();
    });
    $this->assertEquals($builder, $result);
});

test('simple or where not', function () {
    $model = new InstrumentBuilderTestStub();
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->orWhereNot('name', 'foo')->orWhereNot('name', '<>', 'bar');
    $this->assertEquals('select * from "table" where not "name" = ? or not "name" <> ?', $query->toSql());
    $this->assertEquals(['foo', 'bar'], $query->getBindings());
});

test('or where not', function () {
    $nestedQuery = m::mock(Builder::class);
    $nestedRawQuery = dbBuilderMockQueryBuilder();
    $nestedQuery->shouldReceive('getQuery')->once()->andReturn($nestedRawQuery);
    $nestedQuery->shouldReceive('getEagerLoads')->once()->andReturn([]);
    $model = dbBuilderMockModel()->makePartial();
    $model->shouldReceive('newQueryWithoutRelationships')->once()->andReturn($nestedQuery);
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('from');
    $builder->setModel($model);
    $builder->getQuery()->shouldReceive('addNestedWhereQuery')->once()->with($nestedRawQuery, 'or not');
    $nestedQuery->shouldReceive('foo')->once();

    $result = $builder->orWhereNot(function ($query) {
        $query->foo();
    });
    $this->assertEquals($builder, $result);
});

test('real query higher order or where scopes', function () {
    $model = new InstrumentBuilderTestHigherOrderWhereScopeStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->one()->orWhere->two();
    $this->assertSame('select * from "table" where "one" = ? or ("two" = ?)', $query->toSql());
});

test('real query chained higher order or where scopes', function () {
    $model = new InstrumentBuilderTestHigherOrderWhereScopeStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->one()->orWhere->two()->orWhere->three();
    $this->assertSame('select * from "table" where "one" = ? or ("two" = ?) or ("three" = ?)', $query->toSql());
});

test('real query higher order where not scopes', function () {
    $model = new InstrumentBuilderTestHigherOrderWhereScopeStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->one()->whereNot->two();
    $this->assertSame('select * from "table" where "one" = ? and not ("two" = ?)', $query->toSql());
});

test('real query chained higher order where not scopes', function () {
    $model = new InstrumentBuilderTestHigherOrderWhereScopeStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->one()->whereNot->two()->whereNot->three();
    $this->assertSame('select * from "table" where "one" = ? and not ("two" = ?) and not ("three" = ?)', $query->toSql());
});

test('real query higher order or where not scopes', function () {
    $model = new InstrumentBuilderTestHigherOrderWhereScopeStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->one()->orWhereNot->two();
    $this->assertSame('select * from "table" where "one" = ? or not ("two" = ?)', $query->toSql());
});

test('real query chained higher order or where not scopes', function () {
    $model = new InstrumentBuilderTestHigherOrderWhereScopeStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');
    $query = $model->newQuery()->one()->orWhereNot->two()->orWhereNot->three();
    $this->assertSame('select * from "table" where "one" = ? or not ("two" = ?) or not ("three" = ?)', $query->toSql());
});

test('simple where', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('where')->once()->with('foo', '=', 'bar');
    $result = $builder->where('foo', '=', 'bar');
    $this->assertEquals($result, $builder);
});

test('postgres operators where', function () {
    $builder = dbBuilderGetBuilder();
    $builder->getQuery()->shouldReceive('where')->once()->with('foo', '@>', 'bar');
    $result = $builder->where('foo', '@>', 'bar');
    $this->assertEquals($result, $builder);
});

test('where belongs to', function () {
    $related = new InstrumentBuilderTestWhereBelongsToStub([
        'id' => 1,
        'parent_id' => 2,
    ]);

    $parent = new InstrumentBuilderTestWhereBelongsToStub([
        'id' => 2,
        'parent_id' => 1,
    ]);

    $builder = dbBuilderGetBuilder();
    $builder->shouldReceive('from')->with('instrument_builder_test_where_belongs_to_stubs');
    $builder->setModel($related);
    $builder->getQuery()->shouldReceive('whereIn')->once()->with('instrument_builder_test_where_belongs_to_stubs.parent_id', [2], 'and');

    $result = $builder->whereBelongsTo($parent);
    $this->assertEquals($result, $builder);

    $builder = dbBuilderGetBuilder();
    $builder->shouldReceive('from')->with('instrument_builder_test_where_belongs_to_stubs');
    $builder->setModel($related);
    $builder->getQuery()->shouldReceive('whereIn')->once()->with('instrument_builder_test_where_belongs_to_stubs.parent_id', [2], 'and');

    $result = $builder->whereBelongsTo($parent, 'parent');
    $this->assertEquals($result, $builder);

    $parents = new Collection([new InstrumentBuilderTestWhereBelongsToStub([
        'id' => 2,
        'parent_id' => 1,
    ]), new InstrumentBuilderTestWhereBelongsToStub([
        'id' => 3,
        'parent_id' => 1,
    ])]);

    $builder = dbBuilderGetBuilder();
    $builder->shouldReceive('from')->with('instrument_builder_test_where_belongs_to_stubs');
    $builder->setModel($related);
    $builder->getQuery()->shouldReceive('whereIn')->once()->with('instrument_builder_test_where_belongs_to_stubs.parent_id', [2, 3], 'and');

    $result = $builder->whereBelongsTo($parents);
    $this->assertEquals($result, $builder);

    $builder = dbBuilderGetBuilder();
    $builder->shouldReceive('from')->with('instrument_builder_test_where_belongs_to_stubs');
    $builder->setModel($related);
    $builder->getQuery()->shouldReceive('whereIn')->once()->with('instrument_builder_test_where_belongs_to_stubs.parent_id', [2, 3], 'and');

    $result = $builder->whereBelongsTo($parents, 'parent');
    $this->assertEquals($result, $builder);
});

test('where attached to', function () {
    $related = new InstrumentBuilderTestModelFarRelatedStub;
    $related->id = 49;
    $related->name = 'test';

    $builder = InstrumentBuilderTestModelParentStub::whereAttachedTo($related, 'roles');

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where exists (select * from "instrument_builder_test_model_far_related_stubs" inner join "user_role" on "instrument_builder_test_model_far_related_stubs"."id" = "user_role"."related_id" where "instrument_builder_test_model_parent_stubs"."id" = "user_role"."self_id" and "instrument_builder_test_model_far_related_stubs"."id" in (49))', $builder->toSql());
});

test('where attached to collection', function () {
    $model1 = new InstrumentBuilderTestModelParentStub;
    $model1->id = 3;
    $model1->name = 'test3';

    $model2 = new InstrumentBuilderTestModelParentStub;
    $model2->id = 4;
    $model2->name = 'test4';

    $builder = InstrumentBuilderTestModelFarRelatedStub::whereAttachedTo(new Collection([$model1, $model2]), 'roles');

    $this->assertSame('select * from "instrument_builder_test_model_far_related_stubs" where exists (select * from "instrument_builder_test_model_parent_stubs" inner join "user_role" on "instrument_builder_test_model_parent_stubs"."id" = "user_role"."self_id" where "instrument_builder_test_model_far_related_stubs"."id" = "user_role"."related_id" and "instrument_builder_test_model_parent_stubs"."id" in (3, 4))', $builder->toSql());
});

test('delete override', function () {
    $builder = dbBuilderGetBuilder();
    $builder->onDelete(function ($builder) {
        return ['foo' => $builder];
    });
    $this->assertEquals(['foo' => $builder], $builder->delete());
});

test('with count', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withCount('foo');

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_count" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with count and select', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->select('id')->withCount('foo');

    $this->assertSame('select "id", (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_count" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with count second relation with closure', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withCount(['address', 'foo' => function ($query) {
        $query->where('active', false);
    }]);

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "address_count", (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" and "active" = ?) as "foo_count" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with count and merged wheres', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->select('id')->withCount(['activeFoo' => function ($q) {
        $q->where('bam', '>', 'qux');
    }]);

    $this->assertSame('select "id", (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" and "bam" > ? and "active" = ?) as "active_foo_count" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
    $this->assertEquals(['qux', true], $builder->getBindings());
});

test('with count and global scope', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    InstrumentBuilderTestModelCloseRelatedStub::addGlobalScope('withCount', function ($query) {
        return $query->addSelect('id');
    });

    $builder = $model->select('id')->withCount(['foo']);

    // Remove the global scope so it doesn't interfere with any other tests
    InstrumentBuilderTestModelCloseRelatedStub::addGlobalScope('withCount', function ($query) {
        //
    });

    $this->assertSame('select "id", (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_count" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with min', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withMin('foo', 'price');

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select min("instrument_builder_test_model_close_related_stubs"."price") from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_min_price" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with min expression', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withMin('foo', new Expression('price - discount'));

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select min(price - discount) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_min_price_discount" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with min on belongs to many', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withMin('roles', 'id');

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select min("instrument_builder_test_model_far_related_stubs"."id") from "instrument_builder_test_model_far_related_stubs" inner join "user_role" on "instrument_builder_test_model_far_related_stubs"."id" = "user_role"."related_id" where "instrument_builder_test_model_parent_stubs"."id" = "user_role"."self_id") as "roles_min_id" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with min on self related', function () {
    $model = new InstrumentBuilderTestModelSelfRelatedStub;

    $sql = $model->withMin('childFoos', 'created_at')->toSql();

    // alias has a dynamic hash, so replace with a static string for comparison
    $alias = 'self_alias_hash';
    $aliasRegex = '/\b(laravel_reserved_\d)(\b|$)/i';

    $sql = preg_replace($aliasRegex, $alias, $sql);

    $this->assertSame('select "self_related_stubs".*, (select min("self_alias_hash"."created_at") from "self_related_stubs" as "self_alias_hash" where "self_related_stubs"."id" = "self_alias_hash"."parent_id") as "child_foos_min_created_at" from "self_related_stubs"', $sql);
});

test('with max', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withMax('foo', 'price');

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select max("instrument_builder_test_model_close_related_stubs"."price") from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_max_price" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with max expression', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withMax('foo', new Expression('price - discount'));

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select max(price - discount) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_max_price_discount" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with avg', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withAvg('foo', 'price');

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select avg("instrument_builder_test_model_close_related_stubs"."price") from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_avg_price" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('wit avg expression', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withAvg('foo', new Expression('price - discount'));

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select avg(price - discount) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_avg_price_discount" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with count and constraints and having', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->where('bar', 'baz');
    $builder->withCount(['foo' => function ($q) {
        $q->where('bam', '>', 'qux');
    }])->having('foo_count', '>=', 1);

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" and "bam" > ?) as "foo_count" from "instrument_builder_test_model_parent_stubs" where "bar" = ? having "foo_count" >= ?', $builder->toSql());
    $this->assertEquals(['qux', 'baz', 1], $builder->getBindings());
});

test('with count and rename', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withCount('foo as foo_bar');

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_bar" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with count multiple and partial rename', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withCount(['foo as foo_bar', 'foo']);

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_bar", (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_count" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with aggregate alias', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withAggregate('foo', new Expression('TIMESTAMPDIFF(SECOND, `created_at`, `updated_at`)'), 'sum');

    $this->assertSame(
        'select "instrument_builder_test_model_parent_stubs".*, (select sum(TIMESTAMPDIFF(SECOND, `created_at`, `updated_at`)) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_sum_timestampdiffsecond_created_at_updated_at" from "instrument_builder_test_model_parent_stubs"',
        $builder->toSql()
    );
});

test('with aggregate and self relation constrain', function () {
    InstrumentBuilderTestStub::resolveRelationUsing('children', function ($model) {
        return $model->hasMany(InstrumentBuilderTestStub::class, 'parent_id', 'id')->where('enum_value', new stdClass);
    });

    $model = new InstrumentBuilderTestStub;
    dbBuilderMockConnectionForModel($model, '');
    $relationHash = $model->children()->getRelationCountHash(false);

    $builder = $model->withCount('children');

    $this->assertSame(vsprintf('select "table".*, (select count(*) from "table" as "%s" where "table"."id" = "%s"."parent_id" and "enum_value" = ?) as "children_count" from "table"', [$relationHash, $relationHash]), $builder->toSql());
});

test('with exists', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withExists('foo');

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, exists(select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_exists" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with exists and select', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->select('id')->withExists('foo');

    $this->assertSame('select "id", exists(select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_exists" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with exists and merged wheres', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->select('id')->withExists(['activeFoo' => function ($q) {
        $q->where('bam', '>', 'qux');
    }]);

    $this->assertSame('select "id", exists(select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" and "bam" > ? and "active" = ?) as "active_foo_exists" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
    $this->assertEquals(['qux', true], $builder->getBindings());
});

test('with exists and global scope', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    InstrumentBuilderTestModelCloseRelatedStub::addGlobalScope('withExists', function ($query) {
        return $query->addSelect('id');
    });

    $builder = $model->select('id')->withExists(['foo']);

    // Remove the global scope so it doesn't interfere with any other tests
    InstrumentBuilderTestModelCloseRelatedStub::addGlobalScope('withExists', function ($query) {
        //
    });

    $this->assertSame('select "id", exists(select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_exists" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with exists on belongs to many', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withExists('roles');

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, exists(select * from "instrument_builder_test_model_far_related_stubs" inner join "user_role" on "instrument_builder_test_model_far_related_stubs"."id" = "user_role"."related_id" where "instrument_builder_test_model_parent_stubs"."id" = "user_role"."self_id") as "roles_exists" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with exists on self related', function () {
    $model = new InstrumentBuilderTestModelSelfRelatedStub;

    $sql = $model->withExists('childFoos')->toSql();

    // alias has a dynamic hash, so replace with a static string for comparison
    $alias = 'self_alias_hash';
    $aliasRegex = '/\b(laravel_reserved_\d)(\b|$)/i';

    $sql = preg_replace($aliasRegex, $alias, $sql);

    $this->assertSame('select "self_related_stubs".*, exists(select * from "self_related_stubs" as "self_alias_hash" where "self_related_stubs"."id" = "self_alias_hash"."parent_id") as "child_foos_exists" from "self_related_stubs"', $sql);
});

test('with exists and rename', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withExists('foo as foo_bar');

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, exists(select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_bar" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('with exists multiple and partial rename', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->withExists(['foo as foo_bar', 'foo']);

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, exists(select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_bar", exists(select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_exists" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
});

test('has with constraints and having in subquery', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->where('bar', 'baz');
    $builder->whereHas('foo', function ($q) {
        $q->having('bam', '>', 'qux');
    })->where('quux', 'quuux');

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? and exists (select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" having "bam" > ?) and "quux" = ?', $builder->toSql());
    $this->assertEquals(['baz', 'qux', 'quuux'], $builder->getBindings());
});

test('has with constraints with or where and having in subquery', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->where('name', 'larry');
    $builder->whereHas('address', function ($q) {
        $q->where('zipcode', '90210');
        $q->orWhere('zipcode', '90220');
        $q->having('street', '=', 'fooside dr');
    })->where('age', 29);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "name" = ? and exists (select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" and ("zipcode" = ? or "zipcode" = ?) having "street" = ?) and "age" = ?', $builder->toSql());
    $this->assertEquals(['larry', '90210', '90220', 'fooside dr', 29], $builder->getBindings());
});

test('has with constraints with or where and subquery in relation from clause', function () {
    InstrumentBuilderTestModelParentStub::resolveRelationUsing('addressAsExpression', function ($model) {
        return $model->address()->fromSub(InstrumentBuilderTestModelCloseRelatedStub::query(), 'instrument_builder_test_model_close_related_stubs');
    });

    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->where('name', 'larry');
    $builder->whereHas('addressAsExpression', function ($q) {
        $q->where('zipcode', '90210');
        $q->orWhere('zipcode', '90220');
        $q->having('street', '=', 'fooside dr');
    })->where('age', 29);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "name" = ? and exists (select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" and ("zipcode" = ? or "zipcode" = ?) having "street" = ?) and "age" = ?', $builder->toSql());
    $this->assertEquals(['larry', '90210', '90220', 'fooside dr', 29], $builder->getBindings());
});

test('has with constraints and join and having in subquery', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    $builder = $model->where('bar', 'baz');
    $builder->whereHas('foo', function ($q) {
        $q->join('quuuux', function ($j) {
            $j->where('quuuuux', '=', 'quuuuuux');
        });
        $q->having('bam', '>', 'qux');
    })->where('quux', 'quuux');

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? and exists (select * from "instrument_builder_test_model_close_related_stubs" inner join "quuuux" on "quuuuux" = ? where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" having "bam" > ?) and "quux" = ?', $builder->toSql());
    $this->assertEquals(['baz', 'quuuuuux', 'qux', 'quuux'], $builder->getBindings());
});

test('has with constraints and having in subquery with count', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->where('bar', 'baz');
    $builder->whereHas('foo', function ($q) {
        $q->having('bam', '>', 'qux');
    }, '>=', 2)->where('quux', 'quuux');

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? and (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" having "bam" > ?) >= 2 and "quux" = ?', $builder->toSql());
    $this->assertEquals(['baz', 'qux', 'quuux'], $builder->getBindings());
});

test('with count and constraints with binding in select sub', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->newQuery();
    $builder->withCount(['foo' => function ($q) use ($model) {
        $q->selectSub($model->newQuery()->where('bam', '=', 3)->selectRaw('count(0)'), 'bam_3_count');
    }]);

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, (select count(*) from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_count" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
    $this->assertSame([], $builder->getBindings());
});

test('with exists and constraints with binding in select sub', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->newQuery();
    $builder->withExists(['foo' => function ($q) use ($model) {
        $q->selectSub($model->newQuery()->where('bam', '=', 3)->selectRaw('count(0)'), 'bam_3_count');
    }]);

    $this->assertSame('select "instrument_builder_test_model_parent_stubs".*, exists(select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id") as "foo_exists" from "instrument_builder_test_model_parent_stubs"', $builder->toSql());
    $this->assertSame([], $builder->getBindings());
});

test('has nested with constraints', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->whereHas('foo', function ($q) {
        $q->whereHas('bar', function ($q) {
            $q->where('baz', 'bam');
        });
    })->toSql();

    $result = $model->whereHas('foo.bar', function ($q) {
        $q->where('baz', 'bam');
    })->toSql();

    $this->assertEquals($builder, $result);
});

test('has nested', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->whereHas('foo', function ($q) {
        $q->has('bar');
    });

    $result = $model->has('foo.bar')->toSql();

    $this->assertEquals($builder->toSql(), $result);
});

test('has nested with morph to', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    $connection = dbBuilderMockConnectionForModel($model, '');

    $morphToKey = $model->morph()->getMorphType();

    $connection->shouldReceive('select')->once()->andReturn([
        [$morphToKey => InstrumentBuilderTestModelFarRelatedStub::class],
        [$morphToKey => InstrumentBuilderTestModelOtherFarRelatedStub::class],
    ]);

    $builder = $model->orWhereHasMorph('morph', [InstrumentBuilderTestModelFarRelatedStub::class], function ($q) {
        $q->has('baz');
    })->orWhereHasMorph('morph', [InstrumentBuilderTestModelOtherFarRelatedStub::class], function ($q) {
        $q->has('baz');
    });

    $results = $model->has('morph.baz')->toSql();

    // we need to adjust the expected builder because some parathesis are added,
    // which doesn't impact the behavior of the test.

    $builderSql = $builder->toSql();
    $builderSql = str_replace(')))) or ((', '))) or (', $builderSql);

    $this->assertSame($builderSql, $results);
});

test('has nested with morph to and multiple sub relations', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    $connection = dbBuilderMockConnectionForModel($model, '');

    $morphToKey = $model->morph()->getMorphType();

    $connection->shouldReceive('select')->once()->andReturn([
        [$morphToKey => InstrumentBuilderTestModelFarRelatedStub::class],
        [$morphToKey => InstrumentBuilderTestModelOtherFarRelatedStub::class],
    ]);

    $builder = $model->orWhereHasMorph('morph', [InstrumentBuilderTestModelFarRelatedStub::class], function ($q) {
        $q->has('baz.bam');
    })->orWhereHasMorph('morph', [InstrumentBuilderTestModelOtherFarRelatedStub::class], function ($q) {
        $q->has('baz.bam');
    });

    $results = $model->has('morph.baz.bam')->toSql();

    // we need to adjust the expected builder because some parathesis are added,
    // which doesn't impact the behavior of the test.

    $builderSql = $builder->toSql();
    $builderSql = str_replace(')))) or ((', '))) or (', $builderSql);

    $this->assertSame($builderSql, $results);
});

test('or has nested', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->whereHas('foo', function ($q) {
        $q->has('bar');
    })->orWhereHas('foo', function ($q) {
        $q->has('baz');
    });

    $result = $model->has('foo.bar')->orHas('foo.baz')->toSql();

    $this->assertEquals($builder->toSql(), $result);
});

test('self has nested', function () {
    $model = new InstrumentBuilderTestModelSelfRelatedStub;

    $nestedSql = $model->whereHas('parentFoo', function ($q) {
        $q->has('childFoo');
    })->toSql();

    $dotSql = $model->has('parentFoo.childFoo')->toSql();

    // alias has a dynamic hash, so replace with a static string for comparison
    $alias = 'self_alias_hash';
    $aliasRegex = '/\b(laravel_reserved_\d)(\b|$)/i';

    $nestedSql = preg_replace($aliasRegex, $alias, $nestedSql);
    $dotSql = preg_replace($aliasRegex, $alias, $dotSql);

    $this->assertEquals($nestedSql, $dotSql);
});

test('self has nested uses alias', function () {
    $model = new InstrumentBuilderTestModelSelfRelatedStub;

    $sql = $model->has('parentFoo.childFoo')->toSql();

    // alias has a dynamic hash, so replace with a static string for comparison
    $alias = 'self_alias_hash';
    $aliasRegex = '/\b(laravel_reserved_\d)(\b|$)/i';

    $sql = preg_replace($aliasRegex, $alias, $sql);

    $this->assertStringContainsString('"self_alias_hash"."id" = "self_related_stubs"."parent_id"', $sql);
});

test('doesnt have', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->doesntHave('foo');

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not exists (select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id")', $builder->toSql());
});

test('doesnt have nested', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->doesntHave('foo.bar');

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not exists (select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" and exists (select * from "instrument_builder_test_model_far_related_stubs" where "instrument_builder_test_model_close_related_stubs"."id" = "instrument_builder_test_model_far_related_stubs"."instrument_builder_test_model_close_related_stub_id"))', $builder->toSql());
});

test('or doesnt have', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->where('bar', 'baz')->orDoesntHave('foo');

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or not exists (select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id")', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());
});

test('where doesnt have', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->whereDoesntHave('foo', function ($query) {
        $query->where('bar', 'baz');
    });

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not exists (select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" and "bar" = ?)', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());
});

test('or where doesnt have', function () {
    $model = new InstrumentBuilderTestModelParentStub;

    $builder = $model->where('bar', 'baz')->orWhereDoesntHave('foo', function ($query) {
        $query->where('qux', 'quux');
    });

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or not exists (select * from "instrument_builder_test_model_close_related_stubs" where "instrument_builder_test_model_parent_stubs"."foo_id" = "instrument_builder_test_model_close_related_stubs"."id" and "qux" = ?)', $builder->toSql());
    $this->assertEquals(['baz', 'quux'], $builder->getBindings());
});

test('where morphed to', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $relatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $relatedModel->id = 1;

    $builder = $model->whereMorphedTo('morph', $relatedModel);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where (("instrument_builder_test_model_parent_stubs"."morph_type" = ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals([$relatedModel->getMorphClass(), $relatedModel->getKey()], $builder->getBindings());
});

test('where morphed to collection', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $firstRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $firstRelatedModel->id = 1;

    $secondRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $secondRelatedModel->id = 2;

    $builder = $model->whereMorphedTo('morph', new Collection([$firstRelatedModel, $secondRelatedModel]));

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where (("instrument_builder_test_model_parent_stubs"."morph_type" = ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?, ?)))', $builder->toSql());
    $this->assertEquals([$firstRelatedModel->getMorphClass(), $firstRelatedModel->getKey(), $secondRelatedModel->getKey()], $builder->getBindings());
});

test('where morphed to collection with different models', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $firstRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $firstRelatedModel->id = 1;

    $secondRelatedModel = new InstrumentBuilderTestModelFarRelatedStub;
    $secondRelatedModel->id = 2;

    $thirdRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $thirdRelatedModel->id = 3;

    $builder = $model->whereMorphedTo('morph', [$firstRelatedModel, $secondRelatedModel, $thirdRelatedModel]);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where (("instrument_builder_test_model_parent_stubs"."morph_type" = ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?, ?)) or ("instrument_builder_test_model_parent_stubs"."morph_type" = ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals([$firstRelatedModel->getMorphClass(), $firstRelatedModel->getKey(), $thirdRelatedModel->getKey(), $secondRelatedModel->getMorphClass(), $secondRelatedModel->id], $builder->getBindings());
});

test('where morphed to null', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $builder = $model->whereMorphedTo('morph', null);
    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "instrument_builder_test_model_parent_stubs"."morph_type" is null', $builder->toSql());
});

test('where not morphed to', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $relatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $relatedModel->id = 1;

    $builder = $model->whereNotMorphedTo('morph', $relatedModel);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not (("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals([$relatedModel->getMorphClass(), $relatedModel->getKey()], $builder->getBindings());
});

test('where not morphed to collection', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $firstRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $firstRelatedModel->id = 1;

    $secondRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $secondRelatedModel->id = 2;

    $builder = $model->whereNotMorphedTo('morph', new Collection([$firstRelatedModel, $secondRelatedModel]));

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not (("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?, ?)))', $builder->toSql());
    $this->assertEquals([$firstRelatedModel->getMorphClass(), $firstRelatedModel->getKey(), $secondRelatedModel->getKey()], $builder->getBindings());
});

test('where not morphed to collection with different models', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $firstRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $firstRelatedModel->id = 1;

    $secondRelatedModel = new InstrumentBuilderTestModelFarRelatedStub;
    $secondRelatedModel->id = 2;

    $thirdRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $thirdRelatedModel->id = 3;

    $builder = $model->whereNotMorphedTo('morph', [$firstRelatedModel, $secondRelatedModel, $thirdRelatedModel]);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not (("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?, ?)) or ("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals([$firstRelatedModel->getMorphClass(), $firstRelatedModel->getKey(), $thirdRelatedModel->getKey(), $secondRelatedModel->getMorphClass(), $secondRelatedModel->id], $builder->getBindings());
});

test('or where morphed to', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $relatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $relatedModel->id = 1;

    $builder = $model->where('bar', 'baz')->orWhereMorphedTo('morph', $relatedModel);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or (("instrument_builder_test_model_parent_stubs"."morph_type" = ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals(['baz', $relatedModel->getMorphClass(), $relatedModel->getKey()], $builder->getBindings());
});

test('or where morphed to collection', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $firstRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $firstRelatedModel->id = 1;

    $secondRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $secondRelatedModel->id = 2;

    $builder = $model->where('bar', 'baz')->orWhereMorphedTo('morph', new Collection([$firstRelatedModel, $secondRelatedModel]));

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or (("instrument_builder_test_model_parent_stubs"."morph_type" = ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?, ?)))', $builder->toSql());
    $this->assertEquals(['baz', $firstRelatedModel->getMorphClass(), $firstRelatedModel->getKey(), $secondRelatedModel->getKey()], $builder->getBindings());
});

test('or where morphed to collection with different models', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $firstRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $firstRelatedModel->id = 1;

    $secondRelatedModel = new InstrumentBuilderTestModelFarRelatedStub;
    $secondRelatedModel->id = 2;

    $thirdRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $thirdRelatedModel->id = 3;

    $builder = $model->where('bar', 'baz')->orWhereMorphedTo('morph', [$firstRelatedModel, $secondRelatedModel, $thirdRelatedModel]);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or (("instrument_builder_test_model_parent_stubs"."morph_type" = ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?, ?)) or ("instrument_builder_test_model_parent_stubs"."morph_type" = ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals(['baz', $firstRelatedModel->getMorphClass(), $firstRelatedModel->getKey(), $thirdRelatedModel->getKey(), $secondRelatedModel->getMorphClass(), $secondRelatedModel->id], $builder->getBindings());
});

test('or where morphed to null', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $builder = $model->where('bar', 'baz')->orWhereMorphedTo('morph', null);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or "instrument_builder_test_model_parent_stubs"."morph_type" is null', $builder->toSql());
    $this->assertEquals(['baz'], $builder->getBindings());
});

test('or where not morphed to', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $relatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $relatedModel->id = 1;

    $builder = $model->where('bar', 'baz')->orWhereNotMorphedTo('morph', $relatedModel);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or not (("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals(['baz', $relatedModel->getMorphClass(), $relatedModel->getKey()], $builder->getBindings());
});

test('or where not morphed to collection', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $firstRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $firstRelatedModel->id = 1;

    $secondRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $secondRelatedModel->id = 2;

    $builder = $model->where('bar', 'baz')->orWhereNotMorphedTo('morph', new Collection([$firstRelatedModel, $secondRelatedModel]));

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or not (("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?, ?)))', $builder->toSql());
    $this->assertEquals(['baz', $firstRelatedModel->getMorphClass(), $firstRelatedModel->getKey(), $secondRelatedModel->getKey()], $builder->getBindings());
});

test('or where not morphed to collection with different models', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $firstRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $firstRelatedModel->id = 1;

    $secondRelatedModel = new InstrumentBuilderTestModelFarRelatedStub;
    $secondRelatedModel->id = 2;

    $thirdRelatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $thirdRelatedModel->id = 3;

    $builder = $model->where('bar', 'baz')->orWhereNotMorphedTo('morph', [$firstRelatedModel, $secondRelatedModel, $thirdRelatedModel]);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or not (("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?, ?)) or ("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals(['baz', $firstRelatedModel->getMorphClass(), $firstRelatedModel->getKey(), $thirdRelatedModel->getKey(), $secondRelatedModel->getMorphClass(), $secondRelatedModel->id], $builder->getBindings());
});

test('where morphed to class', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $builder = $model->whereMorphedTo('morph', InstrumentBuilderTestModelCloseRelatedStub::class);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "instrument_builder_test_model_parent_stubs"."morph_type" = ?', $builder->toSql());
    $this->assertEquals([InstrumentBuilderTestModelCloseRelatedStub::class], $builder->getBindings());
});

test('where not morphed to class', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $builder = $model->whereNotMorphedTo('morph', InstrumentBuilderTestModelCloseRelatedStub::class);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not ("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ?)', $builder->toSql());
    $this->assertEquals([InstrumentBuilderTestModelCloseRelatedStub::class], $builder->getBindings());
});

test('or where morphed to class', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $builder = $model->where('bar', 'baz')->orWhereMorphedTo('morph', InstrumentBuilderTestModelCloseRelatedStub::class);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or "instrument_builder_test_model_parent_stubs"."morph_type" = ?', $builder->toSql());
    $this->assertEquals(['baz', InstrumentBuilderTestModelCloseRelatedStub::class], $builder->getBindings());
});

test('or where not morphed to class', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    $builder = $model->where('bar', 'baz')->orWhereNotMorphedTo('morph', InstrumentBuilderTestModelCloseRelatedStub::class);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "bar" = ? or not ("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ?)', $builder->toSql());
    $this->assertEquals(['baz', InstrumentBuilderTestModelCloseRelatedStub::class], $builder->getBindings());
});

test('where not morphed to with sqlite', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');

    $relatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $relatedModel->id = 1;

    $builder = $model->whereNotMorphedTo('morph', $relatedModel);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not (("instrument_builder_test_model_parent_stubs"."morph_type" is ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals([$relatedModel->getMorphClass(), $relatedModel->getKey()], $builder->getBindings());
});

test('where not morphed to class with sqlite', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, 'SQLite');

    $builder = $model->whereNotMorphedTo('morph', InstrumentBuilderTestModelCloseRelatedStub::class);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not ("instrument_builder_test_model_parent_stubs"."morph_type" is ?)', $builder->toSql());
    $this->assertEquals([InstrumentBuilderTestModelCloseRelatedStub::class], $builder->getBindings());
});

test('where not morphed to with mysql', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, 'MySql');

    $relatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $relatedModel->id = 1;

    $builder = $model->whereNotMorphedTo('morph', $relatedModel);

    $this->assertSame('select * from `instrument_builder_test_model_parent_stubs` where not ((`instrument_builder_test_model_parent_stubs`.`morph_type` <=> ? and `instrument_builder_test_model_parent_stubs`.`morph_id` in (?)))', $builder->toSql());
    $this->assertEquals([$relatedModel->getMorphClass(), $relatedModel->getKey()], $builder->getBindings());
});

test('where not morphed to class with mysql', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, 'MySql');

    $builder = $model->whereNotMorphedTo('morph', InstrumentBuilderTestModelCloseRelatedStub::class);

    $this->assertSame('select * from `instrument_builder_test_model_parent_stubs` where not (`instrument_builder_test_model_parent_stubs`.`morph_type` <=> ?)', $builder->toSql());
    $this->assertEquals([InstrumentBuilderTestModelCloseRelatedStub::class], $builder->getBindings());
});

test('where not morphed to with postgres', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, 'Postgres');

    $relatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $relatedModel->id = 1;

    $builder = $model->whereNotMorphedTo('morph', $relatedModel);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not (("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ? and "instrument_builder_test_model_parent_stubs"."morph_id" in (?)))', $builder->toSql());
    $this->assertEquals([$relatedModel->getMorphClass(), $relatedModel->getKey()], $builder->getBindings());
});

test('where not morphed to class with postgres', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, 'Postgres');

    $builder = $model->whereNotMorphedTo('morph', InstrumentBuilderTestModelCloseRelatedStub::class);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where not ("instrument_builder_test_model_parent_stubs"."morph_type" is not distinct from ?)', $builder->toSql());
    $this->assertEquals([InstrumentBuilderTestModelCloseRelatedStub::class], $builder->getBindings());
});

test('where not morphed to with sql server', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, 'SqlServer');

    $relatedModel = new InstrumentBuilderTestModelCloseRelatedStub;
    $relatedModel->id = 1;

    $builder = $model->whereNotMorphedTo('morph', $relatedModel);

    $this->assertSame('select * from [instrument_builder_test_model_parent_stubs] where not ((exists (select [instrument_builder_test_model_parent_stubs].[morph_type] intersect select ?) and [instrument_builder_test_model_parent_stubs].[morph_id] in (?)))', $builder->toSql());
    $this->assertEquals([$relatedModel->getMorphClass(), $relatedModel->getKey()], $builder->getBindings());
});

test('where not morphed to class with sql server', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, 'SqlServer');

    $builder = $model->whereNotMorphedTo('morph', InstrumentBuilderTestModelCloseRelatedStub::class);

    $this->assertSame('select * from [instrument_builder_test_model_parent_stubs] where not (exists (select [instrument_builder_test_model_parent_stubs].[morph_type] intersect select ?))', $builder->toSql());
    $this->assertEquals([InstrumentBuilderTestModelCloseRelatedStub::class], $builder->getBindings());
});

test('where morphed to alias', function () {
    $model = new InstrumentBuilderTestModelParentStub;
    dbBuilderMockConnectionForModel($model, '');

    Relation::morphMap([
        'alias' => InstrumentBuilderTestModelCloseRelatedStub::class,
    ]);

    $builder = $model->whereMorphedTo('morph', InstrumentBuilderTestModelCloseRelatedStub::class);

    $this->assertSame('select * from "instrument_builder_test_model_parent_stubs" where "instrument_builder_test_model_parent_stubs"."morph_type" = ?', $builder->toSql());
    $this->assertEquals(['alias'], $builder->getBindings());

    Relation::morphMap([], false);
});

test('where key method with int', function () {
    $model = dbBuilderMockModel();
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $int = 1;

    $model->shouldReceive('getKeyType')->once()->andReturn('int');
    $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '=', $int);

    $builder->whereKey($int);
});

test('where key method with string zero', function () {
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $int = 0;

    $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '=', (string) $int);

    $builder->whereKey($int);
});

test('where key method with string null', function () {
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '=', m::on(function ($argument) {
        return $argument === null;
    }));

    $builder->whereKey(null);
});

test('where key method with array', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->andReturn('int');
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $array = [1, 2, 3];

    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with($keyName, $array);

    $builder->whereKey($array);
});

test('where key method with collection', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->andReturn('int');
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $collection = new Collection([1, 2, 3]);

    $builder->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with($keyName, $collection);

    $builder->whereKey($collection);
});

test('where key method with model', function () {
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '=', m::on(function ($argument) {
        return $argument === '1';
    }));

    $builder->whereKey(new class extends Model
    {
        protected $attributes = ['id' => 1];
    });
});

test('where key not method with string zero', function () {
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $int = 0;

    $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '!=', (string) $int);

    $builder->whereKeyNot($int);
});

test('where key not method with string null', function () {
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '!=', m::on(function ($argument) {
        return $argument === null;
    }));

    $builder->whereKeyNot(null);
});

test('where key not method with int', function () {
    $model = dbBuilderMockModel();
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $int = 1;

    $model->shouldReceive('getKeyType')->once()->andReturn('int');
    $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '!=', $int);

    $builder->whereKeyNot($int);
});

test('where key not method with array', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->andReturn('int');
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $array = [1, 2, 3];

    $builder->getQuery()->shouldReceive('whereIntegerNotInRaw')->once()->with($keyName, $array);

    $builder->whereKeyNot($array);
});

test('where key not method with collection', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getKeyType')->andReturn('int');
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $collection = new Collection([1, 2, 3]);

    $builder->getQuery()->shouldReceive('whereIntegerNotInRaw')->once()->with($keyName, $collection);

    $builder->whereKeyNot($collection);
});

test('where key not method with model', function () {
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '!=', m::on(function ($argument) {
        return $argument === '1';
    }));

    $builder->whereKeyNot(new class extends Model
    {
        protected $attributes = ['id' => 1];
    });
});

test('except method with model', function () {
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $builder->getQuery()->shouldReceive('where')->once()->with($keyName, '!=', m::on(function ($argument) {
        return $argument === '1';
    }));

    $builder->except(new class extends Model
    {
        protected $attributes = ['id' => 1];
    });
});

test('except method with collection of model', function () {
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $builder->getQuery()->shouldReceive('whereNotIn')->once()->with($keyName, m::on(function ($argument) {
        return $argument === [1, 2];
    }));

    $models = new Collection([
        new class extends Model
        {
            protected $attributes = ['id' => 1];
        },
        new class extends Model
        {
            protected $attributes = ['id' => 2];
        },
    ]);

    $builder->except($models);
});

test('except method with array of model', function () {
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder = dbBuilderGetBuilder()->setModel($model);
    $keyName = $model->getQualifiedKeyName();

    $builder->getQuery()->shouldReceive('whereNotIn')->once()->with($keyName, m::on(function ($argument) {
        return $argument === [1, 2];
    }));

    $models = [
        new class extends Model
        {
            protected $attributes = ['id' => 1];
        },
        new class extends Model
        {
            protected $attributes = ['id' => 2];
        },
    ];

    $builder->except($models);
});

test('where in', function () {
    $model = new InstrumentBuilderTestNestedStub;
    dbBuilderMockConnectionForModel($model, '');
    $query = $model->newQuery()->withoutGlobalScopes()->whereIn('foo', $model->newQuery()->select('id'));
    $expected = 'select * from "table" where "foo" in (select "id" from "table" where "table"."deleted_at" is null)';
    $this->assertEquals($expected, $query->toSql());
});

test('latest without column with created at', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getCreatedAtColumn')->andReturn('foo');
    $builder = dbBuilderGetBuilder()->setModel($model);

    $builder->getQuery()->shouldReceive('latest')->once()->with('foo');

    $builder->latest();
});

test('latest without column without created at', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getCreatedAtColumn')->andReturn(null);
    $builder = dbBuilderGetBuilder()->setModel($model);

    $builder->getQuery()->shouldReceive('latest')->once()->with('created_at');

    $builder->latest();
});

test('latest with column', function () {
    $model = dbBuilderMockModel();
    $builder = dbBuilderGetBuilder()->setModel($model);

    $builder->getQuery()->shouldReceive('latest')->once()->with('foo');

    $builder->latest('foo');
});

test('oldest without column with created at', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getCreatedAtColumn')->andReturn('foo');
    $builder = dbBuilderGetBuilder()->setModel($model);

    $builder->getQuery()->shouldReceive('oldest')->once()->with('foo');

    $builder->oldest();
});

test('oldest without column without created at', function () {
    $model = dbBuilderMockModel();
    $model->shouldReceive('getCreatedAtColumn')->andReturn(null);
    $builder = dbBuilderGetBuilder()->setModel($model);

    $builder->getQuery()->shouldReceive('oldest')->once()->with('created_at');

    $builder->oldest();
});

test('oldest with column', function () {
    $model = dbBuilderMockModel();
    $builder = dbBuilderGetBuilder()->setModel($model);

    $builder->getQuery()->shouldReceive('oldest')->once()->with('foo');

    $builder->oldest('foo');
});

test('update', function () {
    Carbon::setTestNow($now = '2017-10-10 10:10:10');

    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $query = new BaseBuilder($connection, new Grammar($connection), m::mock(Processor::class));
    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStub;
    dbBuilderMockConnectionForModel($model, '');
    $builder->setModel($model);
    $builder->getConnection()->shouldReceive('update')->once()
        ->with('update "table" set "foo" = ?, "table"."updated_at" = ?', ['bar', $now])->andReturn(1);

    $result = $builder->update(['foo' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update with timestamp value', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $query = new BaseBuilder($connection, new Grammar($connection), m::mock(Processor::class));
    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStub;
    dbBuilderMockConnectionForModel($model, '');
    $builder->setModel($model);
    $builder->getConnection()->shouldReceive('update')->once()
        ->with('update "table" set "foo" = ?, "table"."updated_at" = ?', ['bar', null])->andReturn(1);

    $result = $builder->update(['foo' => 'bar', 'updated_at' => null]);
    $this->assertEquals(1, $result);
});

test('update with qualified timestamp value', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $query = new BaseBuilder($connection, new Grammar($connection), m::mock(Processor::class));
    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStub;
    dbBuilderMockConnectionForModel($model, '');
    $builder->setModel($model);
    $builder->getConnection()->shouldReceive('update')->once()
        ->with('update "table" set "table"."foo" = ?, "table"."updated_at" = ?', ['bar', null])->andReturn(1);

    $result = $builder->update(['table.foo' => 'bar', 'table.updated_at' => null]);
    $this->assertEquals(1, $result);
});

test('update without timestamp', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $query = new BaseBuilder($connection, new Grammar($connection), m::mock(Processor::class));
    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStubWithoutTimestamp;
    dbBuilderMockConnectionForModel($model, '');
    $builder->setModel($model);
    $builder->getConnection()->shouldReceive('update')->once()
        ->with('update "table" set "foo" = ?', ['bar'])->andReturn(1);

    $result = $builder->update(['foo' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update with alias', function () {
    Carbon::setTestNow($now = '2017-10-10 10:10:10');

    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $query = new BaseBuilder($connection, new Grammar($connection), m::mock(Processor::class));
    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStub;
    dbBuilderMockConnectionForModel($model, '');
    $builder->setModel($model);
    $builder->getConnection()->shouldReceive('update')->once()
        ->with('update "table" as "alias" set "foo" = ?, "alias"."updated_at" = ?', ['bar', $now])->andReturn(1);

    $result = $builder->from('table as alias')->update(['foo' => 'bar']);
    $this->assertEquals(1, $result);
});

test('update with alias with qualified timestamp value', function () {
    Carbon::setTestNow($now = '2017-10-10 10:10:10');

    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $query = new BaseBuilder($connection, new Grammar($connection), m::mock(Processor::class));
    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStub;
    dbBuilderMockConnectionForModel($model, '');
    $builder->setModel($model);
    $builder->getConnection()->shouldReceive('update')->once()
        ->with('update "table" as "alias" set "foo" = ?, "alias"."updated_at" = ?', ['bar', null])->andReturn(1);

    $result = $builder->from('table as alias')->update(['foo' => 'bar', 'alias.updated_at' => null]);
    $this->assertEquals(1, $result);

    Carbon::setTestNow(null);
});

test('upsert', function () {
    Carbon::setTestNow($now = '2017-10-10 10:10:10');

    $query = m::mock(BaseBuilder::class);
    $query->shouldReceive('from')->with('foo_table')->andReturn('foo_table');
    $query->from = 'foo_table';

    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder->setModel($model);

    $query->shouldReceive('upsert')->once()
        ->with([
            ['email' => 'foo', 'name' => 'bar', 'updated_at' => $now, 'created_at' => $now],
            ['name' => 'bar2', 'email' => 'foo2', 'updated_at' => $now, 'created_at' => $now],
        ], ['email'], ['email', 'name', 'updated_at'])->andReturn(2);

    $result = $builder->upsert([['email' => 'foo', 'name' => 'bar'], ['name' => 'bar2', 'email' => 'foo2']], ['email']);

    $this->assertEquals(2, $result);
});

test('touch', function () {
    Carbon::setTestNow($now = '2017-10-10 10:10:10');

    $query = m::mock(BaseBuilder::class);
    $query->shouldReceive('from')->with('foo_table')->andReturn('foo_table');
    $query->from = 'foo_table';

    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder->setModel($model);

    $query->shouldReceive('update')->once()->with(['updated_at' => $now])->andReturn(2);

    $result = $builder->touch();

    $this->assertEquals(2, $result);
});

test('touch with custom column', function () {
    Carbon::setTestNow($now = '2017-10-10 10:10:10');

    $query = m::mock(BaseBuilder::class);
    $query->shouldReceive('from')->with('foo_table')->andReturn('foo_table');
    $query->from = 'foo_table';

    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder->setModel($model);

    $query->shouldReceive('update')->once()->with(['published_at' => $now])->andReturn(2);

    $result = $builder->touch('published_at');

    $this->assertEquals(2, $result);
});

test('touch with multiple columns', function () {
    Carbon::setTestNow($now = '2017-10-10 10:10:10');

    $query = m::mock(BaseBuilder::class);
    $query->shouldReceive('from')->with('foo_table')->andReturn('foo_table');
    $query->from = 'foo_table';

    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStubStringPrimaryKey;
    $builder->setModel($model);

    $query->shouldReceive('update')->once()->with(['published_at' => $now, 'verified_at' => $now])->andReturn(2);

    $result = $builder->touch(['published_at', 'verified_at']);

    $this->assertEquals(2, $result);
});

test('touch without updated at column', function () {
    $query = m::mock(BaseBuilder::class);
    $query->shouldReceive('from')->with('table')->andReturn('table');
    $query->from = 'table';

    $builder = new Builder($query);
    $model = new InstrumentBuilderTestStubWithoutTimestamp;
    $builder->setModel($model);

    $query->shouldNotReceive('update');

    $result = $builder->touch();

    $this->assertFalse($result);
});

test('with casts method', function () {
    $builder = new Builder(dbBuilderMockQueryBuilder());
    $model = dbBuilderMockModel();
    $builder->setModel($model);

    $model->shouldReceive('mergeCasts')->with(['foo' => 'bar'])->once();
    $builder->withCasts(['foo' => 'bar']);
});

test('clone', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $query = new BaseBuilder($connection, new Grammar($connection), m::mock(Processor::class));
    $builder = new Builder($query);
    $builder->select('*')->from('users');
    $clone = $builder->clone()->where('email', 'foo');

    $this->assertNotSame($builder, $clone);
    $this->assertSame('select * from "users"', $builder->toSql());
    $this->assertSame('select * from "users" where "email" = ?', $clone->toSql());
});

test('clone model makes a fresh copy of the model', function () {
    $connection = m::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $query = new BaseBuilder($connection, new Grammar($connection), m::mock(Processor::class));
    $builder = (new Builder($query))->setModel(new InstrumentBuilderTestStub);
    $builder->select('*')->from('users');

    $onCloneCallbackCalledCount = 0;

    $onCloneQuery = null;

    $builder->onClone(function (Builder $query) use (&$onCloneCallbackCalledCount, &$onCloneQuery) {
        $onCloneCallbackCalledCount++;

        $onCloneQuery = $query;
    });

    $clone = $builder->clone()->where('email', 'foo');

    $this->assertNotSame($builder, $clone);
    $this->assertSame('select * from "users"', $builder->toSql());
    $this->assertSame('select * from "users" where "email" = ?', $clone->toSql());

    $this->assertSame(1, $onCloneCallbackCalledCount);
    $this->assertSame($onCloneQuery, $clone);
});

test('to raw sql', function () {
    $query = m::mock(BaseBuilder::class);
    $query->shouldReceive('toRawSql')
        ->andReturn('select * from "users" where "email" = \'foo\'');

    $builder = new Builder($query);

    $this->assertSame('select * from "users" where "email" = \'foo\'', $builder->toRawSql());
});

test('passthru methods calls are not case sensitive', function () {
    $query = m::mock(BaseBuilder::class);

    $mockResponse = 'select 1';
    $query
        ->shouldReceive('toRawSql')
        ->andReturn($mockResponse)
        ->times(3);

    $builder = new Builder($query);

    $this->assertSame('select 1', $builder->TORAWSQL());
    $this->assertSame('select 1', $builder->toRawSql());
    $this->assertSame('select 1', $builder->toRawSQL());
});

test('passthru array elements must all be lowercase', function () {
    $builder = new class(m::mock(BaseBuilder::class)) extends Builder
    {
        // expose protected member for test
        public function getPassthru(): array
        {
            return $this->passthru;
        }
    };

    $passthru = $builder->getPassthru();

    foreach ($passthru as $method) {
        $lowercaseMethod = strtolower($method);

        $this->assertSame(
            $lowercaseMethod,
            $method,
            'Instrument\\Builder relies on lowercase method names in $passthru array to correctly mimic PHP case-insensitivity on method dispatch.'.
                'If you are adding a new method to the $passthru array, make sure the name is lowercased.'
        );
    }
});

test('pipe callback', function () {
    $query = new Builder(new BaseBuilder(
        $connection = new Connection(new PDO('sqlite::memory:')),
        new Grammar($connection),
        new Processor,
    ));

    $result = $query->pipe(fn (Builder $query) => 5);
    $this->assertSame(5, $result);

    $result = $query->pipe(fn (Builder $query) => null);
    $this->assertSame($query, $result);

    $result = $query->pipe(function (Builder $query) {
        //
    });
    $this->assertSame($query, $result);

    $this->assertCount(0, $query->getQuery()->wheres);
    $result = $query->pipe(fn (Builder $query) => $query->where('foo', 'bar'));
    $this->assertSame($query, $result);
    $this->assertCount(1, $query->getQuery()->wheres);
});

function dbBuilderMockConnectionForModel($model, $database)
{
    $grammarClass = 'Voyager\\Database\\Query\\Grammars\\'.$database.'Grammar';
    $processorClass = 'Voyager\\Database\\Query\\Processors\\'.$database.'Processor';
    $processor = new $processorClass;
    $connection = m::mock(Connection::class, ['getPostProcessor' => $processor]);
    $grammar = new $grammarClass($connection);
    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('query')->andReturnUsing(function () use ($connection, $grammar, $processor) {
        return new BaseBuilder($connection, $grammar, $processor);
    });
    $connection->shouldReceive('getDatabaseName')->andReturn('database');
    $resolver = m::mock(ConnectionResolverInterface::class, ['connection' => $connection]);
    $class = get_class($model);
    $class::setConnectionResolver($resolver);

    return $connection;
}

function dbBuilderGetBuilder()
{
    return new Builder(dbBuilderMockQueryBuilder());
}

function dbBuilderMockModel()
{
    $model = m::mock(Model::class);
    $model->shouldReceive('getKeyName')->andReturn('foo');
    $model->shouldReceive('getTable')->andReturn('foo_table');
    $model->shouldReceive('getQualifiedKeyName')->andReturn('foo_table.foo');

    return $model;
}

function dbBuilderMockQueryBuilder()
{
    $query = m::mock(BaseBuilder::class);
    $query->shouldReceive('from')->with('foo_table');

    return $query;
}

class InstrumentBuilderTestStub extends Model
{
    protected $table = 'table';
}

class InstrumentBuilderTestScopeStub extends Model
{
    public function scopeApproved($query)
    {
        $query->where('foo', 'bar');
    }
}

class InstrumentBuilderTestDynamicScopeStub extends Model
{
    public function scopeDynamic($query, $foo = 'foo', $bar = 'bar')
    {
        $query->where($foo, $bar);
    }
}

class InstrumentBuilderTestHigherOrderWhereScopeStub extends Model
{
    protected $table = 'table';

    public function scopeOne($query)
    {
        $query->where('one', 'foo');
    }

    public function scopeTwo($query)
    {
        $query->where('two', 'bar');
    }

    public function scopeThree($query)
    {
        $query->where('three', 'baz');
    }
}

class InstrumentBuilderTestNestedStub extends Model
{
    protected $table = 'table';
    use SoftDeletes;

    public function scopeEmpty($query)
    {
        return $query;
    }
}

class InstrumentBuilderTestPluckStub
{
    protected $attributes;

    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }

    public function __get($key)
    {
        return 'foo_'.$this->attributes[$key];
    }
}

class InstrumentBuilderTestPluckDatesStub extends Model
{
    protected $attributes;

    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }

    protected function asDateTime($value)
    {
        return 'date_'.$value;
    }
}

class InstrumentBuilderTestModelParentStub extends Model
{
    public function foo()
    {
        return $this->belongsTo(InstrumentBuilderTestModelCloseRelatedStub::class);
    }

    public function address()
    {
        return $this->belongsTo(InstrumentBuilderTestModelCloseRelatedStub::class, 'foo_id');
    }

    public function activeFoo()
    {
        return $this->belongsTo(InstrumentBuilderTestModelCloseRelatedStub::class, 'foo_id')->where('active', true);
    }

    public function roles()
    {
        return $this->belongsToMany(
            InstrumentBuilderTestModelFarRelatedStub::class,
            'user_role',
            'self_id',
            'related_id'
        );
    }

    public function morph()
    {
        return $this->morphTo();
    }
}

class InstrumentBuilderTestModelCloseRelatedStub extends Model
{
    public function bar()
    {
        return $this->hasMany(InstrumentBuilderTestModelFarRelatedStub::class);
    }

    public function baz()
    {
        return $this->hasMany(InstrumentBuilderTestModelFarRelatedStub::class);
    }

    public function bam()
    {
        return $this->hasMany(InstrumentBuilderTestModelOtherFarRelatedStub::class);
    }
}

class InstrumentBuilderTestModelFarRelatedStub extends Model
{
    public function roles()
    {
        return $this->belongsToMany(
            InstrumentBuilderTestModelParentStub::class,
            'user_role',
            'related_id',
            'self_id',
        );
    }

    public function baz()
    {
        return $this->belongsTo(InstrumentBuilderTestModelCloseRelatedStub::class);
    }
}

class InstrumentBuilderTestModelOtherFarRelatedStub extends Model
{
    public function roles()
    {
        return $this->belongsToMany(
            InstrumentBuilderTestModelParentStub::class,
            'user_role',
            'related_id',
            'self_id',
        );
    }

    public function baz()
    {
        return $this->belongsTo(InstrumentBuilderTestModelCloseRelatedStub::class);
    }
}

class InstrumentBuilderTestModelSelfRelatedStub extends Model
{
    protected $table = 'self_related_stubs';

    public function parentFoo()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id', 'parent');
    }

    public function childFoo()
    {
        return $this->hasOne(self::class, 'parent_id', 'id');
    }

    public function childFoos()
    {
        return $this->hasMany(self::class, 'parent_id', 'id', 'children');
    }

    public function parentBars()
    {
        return $this->belongsToMany(self::class, 'self_pivot', 'child_id', 'parent_id', 'parent_bars');
    }

    public function childBars()
    {
        return $this->belongsToMany(self::class, 'self_pivot', 'parent_id', 'child_id', 'child_bars');
    }

    public function bazes()
    {
        return $this->hasMany(InstrumentBuilderTestModelFarRelatedStub::class, 'foreign_key', 'id', 'bar');
    }
}

class InstrumentBuilderTestStubWithoutTimestamp extends Model
{
    const UPDATED_AT = null;

    protected $table = 'table';
}

class InstrumentBuilderTestStubStringPrimaryKey extends Model
{
    public $incrementing = false;

    protected $table = 'foo_table';

    protected $keyType = 'string';
}

class InstrumentBuilderTestWhereBelongsToStub extends Model
{
    protected $fillable = [
        'id',
        'parent_id',
    ];

    public function instrumentBuilderTestWhereBelongsToStub()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id', 'parent');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id', 'parent');
    }
}

enum InstrumentBuilderTestBackedEnum: string
{
    case Bar = 'bar';
}

enum InstrumentBuilderTestUnitEnum
{
    case Baz;
}
