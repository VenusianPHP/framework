<?php

namespace Tests\Database;

use Exception;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\HasMany;
use Voyager\Database\Query\Builder as QueryBuilder;
use Voyager\Database\UniqueConstraintViolationException;
use Mockery as m;
use stdClass;

function dbHasManyGetRelation()
{
    $queryBuilder = m::mock(QueryBuilder::class);
    $builder = m::mock(Builder::class, [$queryBuilder]);
    $builder->shouldReceive('whereNotNull')->with('table.foreign_key');
    $builder->shouldReceive('where')->with('table.foreign_key', '=', 1);
    $related = m::mock(Model::class);
    $builder->shouldReceive('getModel')->andReturn($related);
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
    $parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');

    return new HasMany($builder, $parent, 'table.foreign_key', 'id');
}

function dbHasManyExpectForceCreatedModel($relation, $attributes)
{
    $attributes[$relation->getForeignKeyName()] = $relation->getParentKey();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->with($relation->getForeignKeyName())->andReturn($relation->getParentKey());

    $relation->getRelated()->shouldReceive('forceCreate')->once()->with($attributes)->andReturn($model);

    return $model;
}

$dbHasManyExpectNewModel = function ($relation, $attributes = null) {
    $model = $this->getMockBuilder(Model::class)->onlyMethods(['setAttribute', 'save'])->getMock();
    $relation->getRelated()->shouldReceive('newInstance')->with($attributes)->andReturn($model);
    $model->expects($this->once())->method('setAttribute')->with('foreign_key', 1);

    return $model;
};

$dbHasManyExpectCreatedModel = function ($relation, $attributes) use ($dbHasManyExpectNewModel) {
    $model = $dbHasManyExpectNewModel->call($this, $relation, $attributes);
    $model->expects($this->once())->method('save');

    return $model;
};

test('make method does not save new model', function () use ($dbHasManyExpectNewModel) {
    $relation = dbHasManyGetRelation();
    $instance = $dbHasManyExpectNewModel->call($this, $relation, ['name' => 'taylor']);
    $instance->expects($this->never())->method('save');

    $this->assertEquals($instance, $relation->make(['name' => 'taylor']));
});

test('make many creates a related model for each record', function () use ($dbHasManyExpectNewModel) {
    $records = [
        'taylor' => ['name' => 'taylor'],
        'colin' => ['name' => 'colin'],
    ];

    $relation = dbHasManyGetRelation();
    $relation->getRelated()->shouldReceive('newCollection')->once()->andReturn(new Collection);

    $taylor = $dbHasManyExpectNewModel->call($this, $relation, ['name' => 'taylor']);
    $taylor->expects($this->never())->method('save');
    $colin = $dbHasManyExpectNewModel->call($this, $relation, ['name' => 'colin']);
    $colin->expects($this->never())->method('save');

    $instances = $relation->makeMany($records);
    expect($instances)->toBeInstanceOf(Collection::class);
    $this->assertEquals($taylor, $instances[0]);
    $this->assertEquals($colin, $instances[1]);
});

test('create method properly creates new model', function () use ($dbHasManyExpectCreatedModel) {
    $relation = dbHasManyGetRelation();
    $created = $dbHasManyExpectCreatedModel->call($this, $relation, ['name' => 'taylor']);

    $this->assertEquals($created, $relation->create(['name' => 'taylor']));
});

test('force create method properly creates new model', function () {
    $relation = dbHasManyGetRelation();
    $created = dbHasManyExpectForceCreatedModel($relation, ['name' => 'taylor']);

    $this->assertEquals($created, $relation->forceCreate(['name' => 'taylor']));
    expect($created->getAttribute('foreign_key'))->toEqual(1);
});

test('find or new method finds model', function () {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('find')->once()->with('foo', ['*'])->andReturn($model = m::mock(stdClass::class));
    $model->shouldReceive('setAttribute')->never();

    expect($relation->findOrNew('foo'))->toBeInstanceOf(stdClass::class);
});

test('find or new method returns new model with foreign key set', function () {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('find')->once()->with('foo', ['*'])->andReturn(null);
    $relation->getRelated()->shouldReceive('newInstance')->once()->with()->andReturn($model = m::mock(Model::class));
    $model->shouldReceive('setAttribute')->once()->with('foreign_key', 1);

    expect($relation->findOrNew('foo'))->toBeInstanceOf(Model::class);
});

test('first or new method finds first model', function () {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(stdClass::class));
    $model->shouldReceive('setAttribute')->never();

    expect($relation->firstOrNew(['foo']))->toBeInstanceOf(stdClass::class);
});

test('first or new method with values finds first model', function () {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(stdClass::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();
    $model->shouldReceive('setAttribute')->never();

    expect($relation->firstOrNew(['foo' => 'bar'], ['baz' => 'qux']))->toBeInstanceOf(stdClass::class);
});

test('first or new method returns new model with foreign key set', function () use ($dbHasManyExpectNewModel) {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $model = $dbHasManyExpectNewModel->call($this, $relation, ['foo']);

    $this->assertEquals($model, $relation->firstOrNew(['foo']));
});

test('first or new method with values creates new model with foreign key set', function () use ($dbHasManyExpectNewModel) {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $model = $dbHasManyExpectNewModel->call($this, $relation, ['foo' => 'bar', 'baz' => 'qux']);

    $this->assertEquals($model, $relation->firstOrNew(['foo' => 'bar'], ['baz' => 'qux']));
});

test('first or create method finds first model', function () {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(stdClass::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();
    $model->shouldReceive('setAttribute')->never();
    $model->shouldReceive('save')->never();

    expect($relation->firstOrCreate(['foo']))->toBeInstanceOf(stdClass::class);
});

test('first or create method with values finds first model', function () {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(stdClass::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();
    $model->shouldReceive('setAttribute')->never();
    $model->shouldReceive('save')->never();

    expect($relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']))->toBeInstanceOf(stdClass::class);
});

test('first or create method creates new model with foreign key set', function () use ($dbHasManyExpectCreatedModel) {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(fn ($scope) => $scope());
    $model = $dbHasManyExpectCreatedModel->call($this, $relation, ['foo']);

    $this->assertEquals($model, $relation->firstOrCreate(['foo']));
});

test('first or create method with values creates new model with foreign key set', function () use ($dbHasManyExpectCreatedModel) {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(fn ($scope) => $scope());
    $model = $dbHasManyExpectCreatedModel->call($this, $relation, ['foo' => 'bar', 'baz' => 'qux']);

    $this->assertEquals($model, $relation->firstOrCreate(['foo' => 'bar'], ['baz' => 'qux']));
});

test('create or first method with values finds first model', function () {
    $relation = dbHasManyGetRelation();

    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo' => 'bar', 'baz' => 'qux'])->andReturn(m::mock(Model::class, function ($model) {
        $model->shouldReceive('setAttribute')->once()->with('foreign_key', 1);
        $model->shouldReceive('save')->once()->andThrow(
            new UniqueConstraintViolationException('mysql', 'example mysql', [], new Exception('SQLSTATE[23000]: Integrity constraint violation: 1062')),
        );
    }));

    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(function ($scope) {
        return $scope();
    });
    $relation->getQuery()->shouldReceive('useWritePdo')->once()->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo' => 'bar'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(stdClass::class));

    $this->assertInstanceOf(stdClass::class, $found = $relation->createOrFirst(['foo' => 'bar'], ['baz' => 'qux']));
    $this->assertSame($model, $found);
});

test('create or first method creates new model with foreign key set', function () use ($dbHasManyExpectCreatedModel) {
    $relation = dbHasManyGetRelation();

    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(function ($scope) {
        return $scope();
    });
    $relation->getQuery()->shouldReceive('where')->never();
    $relation->getQuery()->shouldReceive('first')->never();
    $model = $dbHasManyExpectCreatedModel->call($this, $relation, ['foo']);

    $this->assertEquals($model, $relation->createOrFirst(['foo']));
});

test('create or first method with values creates new model with foreign key set', function () use ($dbHasManyExpectCreatedModel) {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(function ($scope) {
        return $scope();
    });
    $relation->getQuery()->shouldReceive('where')->never();
    $relation->getQuery()->shouldReceive('first')->never();
    $model = $dbHasManyExpectCreatedModel->call($this, $relation, ['foo' => 'bar', 'baz' => 'qux']);

    $this->assertEquals($model, $relation->createOrFirst(['foo' => 'bar'], ['baz' => 'qux']));
});

test('update or create method finds first model and updates', function () {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn($model = m::mock(stdClass::class));
    $relation->getRelated()->shouldReceive('newInstance')->never();

    $model->wasRecentlyCreated = false;
    $model->shouldReceive('fill')->once()->with(['bar'])->andReturn($model);
    $model->shouldReceive('save')->once();

    expect($relation->updateOrCreate(['foo'], ['bar']))->toBeInstanceOf(stdClass::class);
});

test('update or create method creates new model with foreign key set', function () {
    $relation = dbHasManyGetRelation();
    $relation->getQuery()->shouldReceive('withSavepointIfNeeded')->once()->andReturnUsing(function ($scope) {
        return $scope();
    });
    $relation->getQuery()->shouldReceive('where')->once()->with(['foo'])->andReturn($relation->getQuery());
    $relation->getQuery()->shouldReceive('first')->once()->with()->andReturn(null);
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['foo', 'bar'])->andReturn($model = m::mock(Model::class));

    $model->wasRecentlyCreated = true;
    $model->shouldReceive('save')->once()->andReturn(true);
    $model->shouldReceive('setAttribute')->once()->with('foreign_key', 1);

    expect($relation->updateOrCreate(['foo'], ['bar']))->toBeInstanceOf(Model::class);
});

test('relation upsert fills foreign key', function () {
    $relation = dbHasManyGetRelation();

    $relation->getQuery()->shouldReceive('upsert')->once()->with(
        [
            ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey()],
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
            ['email' => 'foo3', 'name' => 'bar', $relation->getForeignKeyName() => $relation->getParentKey()],
            ['name' => 'bar2', 'email' => 'foo2', $relation->getForeignKeyName() => $relation->getParentKey()],
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

test('relation is properly initialized', function () {
    $relation = dbHasManyGetRelation();
    $model = m::mock(Model::class);
    $relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function ($array = []) {
        return new Collection($array);
    });
    $model->shouldReceive('setRelation')->once()->with('foo', m::type(Collection::class));
    $models = $relation->initRelation([$model], 'foo');

    $this->assertEquals([$model], $models);
});

test('eager constraints are properly added', function () {
    $relation = dbHasManyGetRelation();
    $relation->getParent()->shouldReceive('getKeyName')->once()->andReturn('id');
    $relation->getParent()->shouldReceive('getKeyType')->once()->andReturn('int');
    $relation->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('table.foreign_key', [1, 2]);
    $model1 = new InstrumentHasManyModelStub;
    $model1->id = 1;
    $model2 = new InstrumentHasManyModelStub;
    $model2->id = 2;
    $relation->addEagerConstraints([$model1, $model2]);
});

test('eager constraints are properly added with string key', function () {
    $relation = dbHasManyGetRelation();
    $relation->getParent()->shouldReceive('getKeyName')->once()->andReturn('id');
    $relation->getParent()->shouldReceive('getKeyType')->once()->andReturn('string');
    $relation->getQuery()->shouldReceive('whereIn')->once()->with('table.foreign_key', [1, 2]);
    $model1 = new InstrumentHasManyModelStub;
    $model1->id = 1;
    $model2 = new InstrumentHasManyModelStub;
    $model2->id = 2;
    $relation->addEagerConstraints([$model1, $model2]);
});

test('models are properly matched to parents', function () {
    $relation = dbHasManyGetRelation();

    $result1 = new InstrumentHasManyModelStub;
    $result1->foreign_key = 1;
    $result2 = new InstrumentHasManyModelStub;
    $result2->foreign_key = 2;
    $result3 = new InstrumentHasManyModelStub;
    $result3->foreign_key = 2;

    $model1 = new InstrumentHasManyModelStub;
    $model1->id = 1;
    $model2 = new InstrumentHasManyModelStub;
    $model2->id = 2;
    $model3 = new InstrumentHasManyModelStub;
    $model3->id = 3;

    $relation->getRelated()->shouldReceive('newCollection')->andReturnUsing(function ($array) {
        return new Collection($array);
    });
    $models = $relation->match([$model1, $model2, $model3], new Collection([$result1, $result2, $result3]), 'foo');

    expect($models[0]->foo[0]->foreign_key)->toEqual(1)
        ->and($models[0]->foo)->toHaveCount(1)
        ->and($models[1]->foo[0]->foreign_key)->toEqual(2)
        ->and($models[1]->foo[1]->foreign_key)->toEqual(2)
        ->and($models[1]->foo)->toHaveCount(2)
        ->and($models[2]->foo)->toBeNull();
});

test('create many creates a related model for each record', function () use ($dbHasManyExpectCreatedModel) {
    $records = [
        'taylor' => ['name' => 'taylor'],
        'colin' => ['name' => 'colin'],
    ];

    $relation = dbHasManyGetRelation();
    $relation->getRelated()->shouldReceive('newCollection')->once()->andReturn(new Collection);

    $taylor = $dbHasManyExpectCreatedModel->call($this, $relation, ['name' => 'taylor']);
    $colin = $dbHasManyExpectCreatedModel->call($this, $relation, ['name' => 'colin']);

    $instances = $relation->createMany($records);
    expect($instances)->toBeInstanceOf(Collection::class);
    $this->assertEquals($taylor, $instances[0]);
    $this->assertEquals($colin, $instances[1]);
});

class InstrumentHasManyModelStub extends Model
{
    public $foreign_key = 'foreign.value';
}
