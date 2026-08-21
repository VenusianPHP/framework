<?php

namespace Tests\Database;

use Voyager\Contracts\Database\Query\Expression;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\HasOne;
use Voyager\Database\Query\Builder as BaseBuilder;
use Mockery as m;

function dbHasOneGetRelation($test)
{
    $test->builder = m::mock(Builder::class);
    $test->builder->shouldReceive('whereNotNull')->with('table.foreign_key');
    $test->builder->shouldReceive('where')->with('table.foreign_key', '=', 1);
    $test->related = m::mock(Model::class);
    $test->builder->shouldReceive('getModel')->andReturn($test->related);
    $test->parent = m::mock(Model::class);
    $test->parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $test->parent->shouldReceive('getAttribute')->with('username')->andReturn('taylor');
    $test->parent->shouldReceive('getCreatedAtColumn')->andReturn('created_at');
    $test->parent->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
    $test->parent->shouldReceive('newQueryWithoutScopes')->andReturn($test->builder);

    return new HasOne($test->builder, $test->parent, 'table.foreign_key', 'id');
}

test('has one with default', function () {
    $relation = dbHasOneGetRelation($this)->withDefault();

    $this->builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentHasOneModelStub;

    $this->related->shouldReceive('newInstance')->once()->andReturn($newModel);

    expect($relation->getResults())->toBe($newModel);

    expect($newModel->getAttribute('foreign_key'))->toBe(1);
});

test('has one with dynamic default', function () {
    $relation = dbHasOneGetRelation($this)->withDefault(function ($newModel) {
        $newModel->username = 'taylor';
    });

    $this->builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentHasOneModelStub;

    $this->related->shouldReceive('newInstance')->once()->andReturn($newModel);

    expect($relation->getResults())->toBe($newModel);

    expect($newModel->username)->toBe('taylor');

    expect($newModel->getAttribute('foreign_key'))->toBe(1);
});

test('has one with dynamic default use parent model', function () {
    $relation = dbHasOneGetRelation($this)->withDefault(function ($newModel, $parentModel) {
        $newModel->username = $parentModel->username;
    });

    $this->builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentHasOneModelStub;

    $this->related->shouldReceive('newInstance')->once()->andReturn($newModel);

    expect($relation->getResults())->toBe($newModel);

    expect($newModel->username)->toBe('taylor');

    expect($newModel->getAttribute('foreign_key'))->toBe(1);
});

test('has one with array default', function () {
    $attributes = ['username' => 'taylor'];

    $relation = dbHasOneGetRelation($this)->withDefault($attributes);

    $this->builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentHasOneModelStub;

    $this->related->shouldReceive('newInstance')->once()->andReturn($newModel);

    expect($relation->getResults())->toBe($newModel);

    expect($newModel->username)->toBe('taylor');

    expect($newModel->getAttribute('foreign_key'))->toBe(1);
});

test('make method does not save new model', function () {
    $relation = dbHasOneGetRelation($this);
    $instance = $this->getMockBuilder(Model::class)->onlyMethods(['save', 'newInstance', 'setAttribute'])->getMock();
    $relation->getRelated()->shouldReceive('newInstance')->with(['name' => 'taylor'])->andReturn($instance);
    $instance->expects($this->once())->method('setAttribute')->with('foreign_key', 1);
    $instance->expects($this->never())->method('save');

    $this->assertEquals($instance, $relation->make(['name' => 'taylor']));
});

test('save method sets foreign key on model', function () {
    $relation = dbHasOneGetRelation($this);
    $mockModel = $this->getMockBuilder(Model::class)->onlyMethods(['save'])->getMock();
    $mockModel->expects($this->once())->method('save')->willReturn(true);
    $result = $relation->save($mockModel);

    $attributes = $result->getAttributes();
    expect($attributes['foreign_key'])->toEqual(1);
});

test('create method properly creates new model', function () {
    $relation = dbHasOneGetRelation($this);
    $created = $this->getMockBuilder(Model::class)->onlyMethods(['save', 'getKey', 'setAttribute'])->getMock();
    $created->expects($this->once())->method('save')->willReturn(true);
    $relation->getRelated()->shouldReceive('newInstance')->once()->with(['name' => 'taylor'])->andReturn($created);
    $created->expects($this->once())->method('setAttribute')->with('foreign_key', 1);

    $this->assertEquals($created, $relation->create(['name' => 'taylor']));
});

test('force create method properly creates new model', function () {
    $relation = dbHasOneGetRelation($this);
    $attributes = ['name' => 'taylor', $relation->getForeignKeyName() => $relation->getParentKey()];

    $created = m::mock(Model::class);
    $created->shouldReceive('getAttribute')->with($relation->getForeignKeyName())->andReturn($relation->getParentKey());

    $relation->getRelated()->shouldReceive('forceCreate')->once()->with($attributes)->andReturn($created);

    $this->assertEquals($created, $relation->forceCreate(['name' => 'taylor']));
    expect($created->getAttribute('foreign_key'))->toEqual(1);
});

test('relation is properly initialized', function () {
    $relation = dbHasOneGetRelation($this);
    $model = m::mock(Model::class);
    $model->shouldReceive('setRelation')->once()->with('foo', null);
    $models = $relation->initRelation([$model], 'foo');

    $this->assertEquals([$model], $models);
});

test('eager constraints are properly added', function () {
    $relation = dbHasOneGetRelation($this);
    $relation->getParent()->shouldReceive('getKeyName')->once()->andReturn('id');
    $relation->getParent()->shouldReceive('getKeyType')->once()->andReturn('int');
    $relation->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('table.foreign_key', [1, 2]);
    $model1 = new InstrumentHasOneModelStub;
    $model1->id = 1;
    $model2 = new InstrumentHasOneModelStub;
    $model2->id = 2;
    $relation->addEagerConstraints([$model1, $model2]);
});

test('models are properly matched to parents', function () {
    $relation = dbHasOneGetRelation($this);

    $result1 = new InstrumentHasOneModelStub;
    $result1->foreign_key = 1;
    $result2 = new InstrumentHasOneModelStub;
    $result2->foreign_key = 2;
    $result3 = new InstrumentHasOneModelStub;
    $result3->foreign_key = new class
    {
        public function __toString()
        {
            return '4';
        }
    };

    $model1 = new InstrumentHasOneModelStub;
    $model1->id = 1;
    $model2 = new InstrumentHasOneModelStub;
    $model2->id = 2;
    $model3 = new InstrumentHasOneModelStub;
    $model3->id = 3;
    $model4 = new InstrumentHasOneModelStub;
    $model4->id = 4;

    $models = $relation->match([$model1, $model2, $model3, $model4], new Collection([$result1, $result2, $result3]), 'foo');

    expect($models[0]->foo->foreign_key)->toEqual(1)
        ->and($models[1]->foo->foreign_key)->toEqual(2)
        ->and($models[2]->foo)->toBeNull()
        ->and((string) $models[3]->foo->foreign_key)->toBe('4');
});

test('relation count query can be built', function () {
    $relation = dbHasOneGetRelation($this);
    $builder = m::mock(Builder::class);

    $baseQuery = m::mock(BaseBuilder::class);
    $baseQuery->from = 'one';
    $parentQuery = m::mock(BaseBuilder::class);
    $parentQuery->from = 'two';

    $builder->shouldReceive('getQuery')->once()->andReturn($baseQuery);
    $builder->shouldReceive('getQuery')->once()->andReturn($parentQuery);

    $builder->shouldReceive('select')->once()->with(m::type(Expression::class))->andReturnSelf();
    $relation->getParent()->shouldReceive('qualifyColumn')->andReturn('table.id');
    $builder->shouldReceive('whereColumn')->once()->with('table.id', '=', 'table.foreign_key')->andReturn($baseQuery);
    $baseQuery->shouldReceive('setBindings')->once()->with([], 'select');

    $relation->getRelationExistenceCountQuery($builder, $builder);
});

test('is not null', function () {
    $relation = dbHasOneGetRelation($this);

    $this->related->shouldReceive('getTable')->never();
    $this->related->shouldReceive('getConnectionName')->never();

    expect($relation->is(null))->toBeFalse();
});

test('is model', function () {
    $relation = dbHasOneGetRelation($this);

    $this->related->shouldReceive('getTable')->once()->andReturn('table');
    $this->related->shouldReceive('getConnectionName')->once()->andReturn('connection');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('table');
    $model->shouldReceive('getConnectionName')->once()->andReturn('connection');

    expect($relation->is($model))->toBeTrue();
});

test('is model with string related key', function () {
    $relation = dbHasOneGetRelation($this);

    $this->related->shouldReceive('getTable')->once()->andReturn('table');
    $this->related->shouldReceive('getConnectionName')->once()->andReturn('connection');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('1');
    $model->shouldReceive('getTable')->once()->andReturn('table');
    $model->shouldReceive('getConnectionName')->once()->andReturn('connection');

    expect($relation->is($model))->toBeTrue();
});

test('is not model with null related key', function () {
    $relation = dbHasOneGetRelation($this);

    $this->related->shouldReceive('getTable')->never();
    $this->related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(null);
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with another related key', function () {
    $relation = dbHasOneGetRelation($this);

    $this->related->shouldReceive('getTable')->never();
    $this->related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(2);
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with another table', function () {
    $relation = dbHasOneGetRelation($this);

    $this->related->shouldReceive('getTable')->once()->andReturn('table');
    $this->related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('table.two');
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with another connection', function () {
    $relation = dbHasOneGetRelation($this);

    $this->related->shouldReceive('getTable')->once()->andReturn('table');
    $this->related->shouldReceive('getConnectionName')->once()->andReturn('connection');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('table');
    $model->shouldReceive('getConnectionName')->once()->andReturn('connection.two');

    expect($relation->is($model))->toBeFalse();
});

class InstrumentHasOneModelStub extends Model
{
    public $foreign_key = 'foreign.value';
}
