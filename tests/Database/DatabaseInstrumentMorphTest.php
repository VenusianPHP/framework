<?php

use Foo\Bar\InstrumentModelNamespacedStub;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\MorphMany;
use Voyager\Database\Instrument\Relations\MorphOne;
use Voyager\Database\Instrument\Relations\Relation;
use Voyager\Database\Query\Builder as QueryBuilder;
use Voyager\Database\UniqueConstraintViolationException;
use Mockery as m;

function dbMorphGetOneRelation()
{
    $queryBuilder = m::mock(QueryBuilder::class);
    $builder = m::mock(Builder::class, [$queryBuilder]);
    $builder->shouldReceive('whereNotNull')->once()->with('table.morph_id');
    $builder->shouldReceive('where')->once()->with('table.morph_id', '=', 1);
    $related = m::mock(Model::class);
    $builder->shouldReceive('getModel')->andReturn($related);
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $parent->shouldReceive('getMorphClass')->andReturn(get_class($parent));
    $builder->shouldReceive('where')->once()->with('table.morph_type', get_class($parent));

    return new MorphOne($builder, $parent, 'table.morph_type', 'table.morph_id', 'id');
}

function dbMorphGetManyRelation()
{
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('whereNotNull')->once()->with('table.morph_id');
    $builder->shouldReceive('where')->once()->with('table.morph_id', '=', 1);
    $related = m::mock(Model::class);
    $builder->shouldReceive('getModel')->andReturn($related);
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $parent->shouldReceive('getMorphClass')->andReturn(get_class($parent));
    $builder->shouldReceive('where')->once()->with('table.morph_type', get_class($parent));

    return new MorphMany($builder, $parent, 'table.morph_type', 'table.morph_id', 'id');
}

function dbMorphGetNamespacedRelation($alias)
{
    require_once __DIR__.'/stubs/InstrumentModelNamespacedStub.php';

    Relation::morphMap([
        $alias => InstrumentModelNamespacedStub::class,
    ]);

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('whereNotNull')->once()->with('table.morph_id');
    $builder->shouldReceive('where')->once()->with('table.morph_id', '=', 1);
    $related = m::mock(Model::class);
    $builder->shouldReceive('getModel')->andReturn($related);
    $parent = m::mock(InstrumentModelNamespacedStub::class);
    $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $parent->shouldReceive('getMorphClass')->andReturn($alias);
    $builder->shouldReceive('where')->once()->with('table.morph_type', $alias);

    return new MorphOne($builder, $parent, 'table.morph_type', 'table.morph_id', 'id');
}

afterEach(function () {
    Relation::morphMap([], false);
});

test('morph one sets proper constraints', function () {
    dbMorphGetOneRelation();
});

test('morph one eager constraints are properly added', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getParent()->shouldReceive('getKeyName')->once()->andReturn('id');
    $relation->getParent()->shouldReceive('getKeyType')->once()->andReturn('string');
    $relation->getQuery()->shouldReceive('whereIn')->once()->with('table.morph_id', [1, 2]);
    $relation->getQuery()->shouldReceive('where')->once()->with('table.morph_type', get_class($relation->getParent()));

    $model1 = new InstrumentMorphResetModelStub;
    $model1->id = 1;
    $model2 = new InstrumentMorphResetModelStub;
    $model2->id = 2;
    $relation->addEagerConstraints([$model1, $model2]);
});

// Note that the tests are the exact same for morph many because the classes share this code...
// Will still test to be safe.
test('morph many sets proper constraints', function () {
    dbMorphGetManyRelation();
});

test('morph many eager constraints are properly added', function () {
    $relation = dbMorphGetManyRelation();
    $relation->getParent()->shouldReceive('getKeyName')->once()->andReturn('id');
    $relation->getParent()->shouldReceive('getKeyType')->once()->andReturn('int');
    $relation->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('table.morph_id', [1, 2]);
    $relation->getQuery()->shouldReceive('where')->once()->with('table.morph_type', get_class($relation->getParent()));

    $model1 = new InstrumentMorphResetModelStub;
    $model1->id = 1;
    $model2 = new InstrumentMorphResetModelStub;
    $model2->id = 2;
    $relation->addEagerConstraints([$model1, $model2]);
});

test('morph relation upsert fills foreign key', function () {
    $relation = dbMorphGetManyRelation();

    $relation->getQuery()->shouldReceive('upsert')->once()->with(
        [
            ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey(), $relation->getMorphType() => $relation->getMorphClass()],
        ],
        ['email'],
        ['name']
    );

    $relation->upsert(
        ['email' => 'foo3', 'name' => 'bar'],
        ['email'],
        ['name']
    );

    $relation->getQuery()->shouldReceive('upsert')->once()->with(
        [
            ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey(), $relation->getMorphType() => $relation->getMorphClass()],
            ['name' => 'bar2', 'email' => 'foo2', $relation->getForeignKeyName() => $relation->getParentKey(), $relation->getMorphType() => $relation->getMorphClass()],
        ],
        ['email'],
        ['name']
    );

    $relation->upsert(
        [
            ['email' => 'foo3', 'name' => 'bar'],
            ['name' => 'bar2', 'email' => 'foo2'],
        ],
        ['email'],
        ['name']
    );
});

test('make function on morph', function () {
    $_SERVER['__instrument.saved'] = false;
    // Doesn't matter which relation type we use since they share the code...
    $relation = dbMorphGetOneRelation();
    $instance = m::mock(Model::class);
    $instance->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $instance->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $instance->shouldReceive('save')->never();
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['name' => 'taylor'])->andReturn($instance);

    expect($relation->make(['name' => 'taylor']))->toEqual($instance);
});

test('create function on morph', function () {
    // Doesn't matter which relation type we use since they share the code...
    $relation = dbMorphGetOneRelation();
    $created = m::mock(Model::class);
    $created->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $created->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['name' => 'taylor'])->andReturn($created);
    $created->shouldReceive('save')->once()->andReturn(true);

    expect($relation->create(['name' => 'taylor']))->toEqual($created);
});

test('find or new method finds model', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('find')->once()->with('foo', ['*'])->andReturn($model = m::mock(Model::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();
    $model->shouldReceive('setAttribute')->never();
    $model->shouldReceive('save')->never();

    expect($relation->findOrNew('foo'))->toBeInstanceOf(Model::class);
});

test('find or new method returns new model with morph keys set', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('find')->once()->with('foo', ['*'])->andReturn(null);
    $relation->getRelated()->shouldReceive('newInstance')->once()->with()->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->never();

    expect($relation->findOrNew('foo'))->toBeInstanceOf(Model::class);
});

test('first or new method finds first model', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(Model::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();
    $model->shouldReceive('setAttribute')->never();
    $model->shouldReceive('save')->never();

    expect($relation->firstOrNew(['foo']))->toBeInstanceOf(Model::class);
});

test('first or new method with value finds first model', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(Model::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();
    $model->shouldReceive('setAttribute')->never();
    $model->shouldReceive('save')->never();

    expect($relation->firstOrNew(['foo' => 'bar'], ['baz' => 'qux']))->toBeInstanceOf(Model::class);
});

test('first or new method returns new model with morph keys set', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo'])->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->never();

    expect($relation->firstOrNew(['foo']))->toBeInstanceOf(Model::class);
});

test('first or new method with values returns new model with morph keys set', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo' => 'bar', 'baz' => 'qux'])->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->never();

    expect($relation->firstOrNew(['foo' => 'bar'], ['baz' => 'qux']))->toBeInstanceOf(Model::class);
});

test('first or create method finds first model', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(Model::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();
    $model->shouldReceive('setAttribute')->never();
    $model->shouldReceive('save')->never();

    expect($relation->firstOrCreate(['foo']))->toBeInstanceOf(Model::class);
});

test('first or create method with values finds first model', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(Model::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();
    $model->shouldReceive('setAttribute')->never();
    $model->shouldReceive('save')->never();

    expect($relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']))->toBeInstanceOf(Model::class);
});

test('first or create method creates new morph model', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(fn ($scope) => $scope());
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo'])->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->once()->andReturn(true);

    expect($relation->firstOrCreate(['foo']))->toBeInstanceOf(Model::class);
});

test('first or create method with values creates new morph model', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(fn ($scope) => $scope());
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo' => 'bar', 'baz' => 'qux'])->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->once()->andReturn(true);

    expect($relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']))->toBeInstanceOf(Model::class);
});

test('create or first method finds first model', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo'])->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->once()->andThrow(
        new UniqueConstraintViolationException('mysql', 'example mysql', [], new Exception('SQLSTATE[23000]: Integrity constraint violation: 1062')),
    );

    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(function ($scope) {
        return $scope();
    });
    $relation->getQuery()->shouldReceive('useWritePdo')->once()->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(Model::class));

    expect($relation->createOrFirst(['foo']))->toBeInstanceOf(Model::class);
});

test('create or first method with values finds first model', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo' => 'bar', 'baz' => 'qux'])->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->once()->andThrow(
        new UniqueConstraintViolationException('mysql', 'example mysql', [], new Exception('SQLSTATE[23000]: Integrity constraint violation: 1062')),
    );

    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(function ($scope) {
        return $scope();
    });
    $relation->getQuery()->shouldReceive('useWritePdo')->once()->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(Model::class));

    expect($relation->createOrFirst(['foo' => 'bar'], ['baz' => 'qux']))->toBeInstanceOf(Model::class);
});

test('create or first method creates new morph model', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo'])->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->once()->andReturn(true);

    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(function ($scope) {
        return $scope();
    });
    $relation->getQuery()->shouldReceive('where')->never();
    $relation->getQuery()->shouldReceive('first')->never();

    expect($relation->createOrFirst(['foo']))->toBeInstanceOf(Model::class);
});

test('create or first method with values creates new morph model', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo' => 'bar', 'baz' => 'qux'])->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->once()->andReturn(true);

    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(function ($scope) {
        return $scope();
    });
    $relation->getQuery()->shouldReceive('where')->never();
    $relation->getQuery()->shouldReceive('first')->never();

    expect($relation->createOrFirst(['foo' => 'bar'], ['baz' => 'qux']))->toBeInstanceOf(Model::class);
});

test('update or create method finds first model and updates', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(Model::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();

    $model->wasRecentlyCreated = false;
    $model->shouldReceive('setAttribute')->never();
    $model->shouldReceive('fill')->once()->with(['bar'])->andReturn($model);
    $model->shouldReceive('save')->once();

    expect($relation->updateOrCreate(['foo'], ['bar']))->toBeInstanceOf(Model::class);
});

test('update or create method creates new morph model', function () {
    $relation = dbMorphGetOneRelation();
    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(function ($scope) {
        return $scope();
    });
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo', 'bar'])->andReturn($model = m::mock(Model::class));

    $model->wasRecentlyCreated = true;
    $model->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $model->shouldReceive('setAttribute')->once()->with('morph_type', get_class($relation->getParent()));
    $model->shouldReceive('save')->once()->andReturn(true);

    expect($relation->updateOrCreate(['foo'], ['bar']))->toBeInstanceOf(Model::class);
});

test('create function on namespaced morph', function () {
    $relation = dbMorphGetNamespacedRelation('namespace');
    $created = m::mock(Model::class);
    $created->shouldReceive('setAttribute')->once()->with('morph_id', 1);
    $created->shouldReceive('setAttribute')->once()->with('morph_type', 'namespace');
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['name' => 'taylor'])->andReturn($created);
    $created->shouldReceive('save')->once()->andReturn(true);

    expect($relation->create(['name' => 'taylor']))->toEqual($created);
});

test('is not null', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('getTable')->never();
    $relation->getRelated()->shouldReceive('getConnectionName')->never();

    expect($relation->is(null))->toBeFalse();
});

test('is model', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('getTable')->once()->andReturn('table');
    $relation->getRelated()->shouldReceive('getConnectionName')->once()->andReturn('connection');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('morph_id')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('table');
    $model->shouldReceive('getConnectionName')->once()->andReturn('connection');

    expect($relation->is($model))->toBeTrue();
});

test('is model with string related key', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('getTable')->once()->andReturn('table');
    $relation->getRelated()->shouldReceive('getConnectionName')->once()->andReturn('connection');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('morph_id')->andReturn('1');
    $model->shouldReceive('getTable')->once()->andReturn('table');
    $model->shouldReceive('getConnectionName')->once()->andReturn('connection');

    expect($relation->is($model))->toBeTrue();
});

test('is not model with null related key', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('getTable')->never();
    $relation->getRelated()->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('morph_id')->andReturn(null);
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with another related key', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('getTable')->never();
    $relation->getRelated()->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('morph_id')->andReturn(2);
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with another table', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('getTable')->once()->andReturn('table');
    $relation->getRelated()->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('morph_id')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('table.two');
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with another connection', function () {
    $relation = dbMorphGetOneRelation();

    $relation->getRelated()->shouldReceive('getTable')->once()->andReturn('table');
    $relation->getRelated()->shouldReceive('getConnectionName')->once()->andReturn('connection');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('morph_id')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('table');
    $model->shouldReceive('getConnectionName')->once()->andReturn('connection.two');

    expect($relation->is($model))->toBeFalse();
});

class InstrumentMorphResetModelStub extends Model
{
    //
}
