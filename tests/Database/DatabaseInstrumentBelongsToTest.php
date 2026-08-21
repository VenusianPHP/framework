<?php

namespace Tests\Database;

use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\BelongsTo;
use Tests\Database\Fixtures\Enums\Bar;
use Mockery as m;

/**
 * Build a BelongsTo relation backed by a mocked query builder and related model.
 *
 * @return array{0: BelongsTo, 1: Builder, 2: Model}
 */
function dbBelongsToGetRelation($parent = null, $keyType = 'int'): array
{
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('where')->with('relation.id', '=', 'foreign.value');
    $related = m::mock(Model::class);
    $related->shouldReceive('getKeyType')->andReturn($keyType);
    $related->shouldReceive('getKeyName')->andReturn('id');
    $related->shouldReceive('getTable')->andReturn('relation');
    $related->shouldReceive('qualifyColumn')->andReturnUsing(fn (string $column) => "relation.{$column}");
    $builder->shouldReceive('getModel')->andReturn($related);
    $parent = $parent ?: new InstrumentBelongsToModelStub;

    return [new BelongsTo($builder, $parent, 'foreign_key', 'id', 'relation'), $builder, $related];
}

test('belongs to with default', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();
    $relation = $relation->withDefault();

    $builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentBelongsToModelStub;

    $related->shouldReceive('newInstance')->once()->andReturn($newModel);

    $this->assertSame($newModel, $relation->getResults());
});

test('belongs to with dynamic default', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();
    $relation = $relation->withDefault(function ($newModel) {
        $newModel->username = 'taylor';
    });

    $builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentBelongsToModelStub;

    $related->shouldReceive('newInstance')->once()->andReturn($newModel);

    $this->assertSame($newModel, $relation->getResults());

    $this->assertSame('taylor', $newModel->username);
});

test('belongs to with array default', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();
    $relation = $relation->withDefault(['username' => 'taylor']);

    $builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentBelongsToModelStub;

    $related->shouldReceive('newInstance')->once()->andReturn($newModel);

    $this->assertSame($newModel, $relation->getResults());

    $this->assertSame('taylor', $newModel->username);
});

test('eager constraints are properly added', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();
    $relation->getRelated()->shouldReceive('getKeyName')->andReturn('id');
    $relation->getRelated()->shouldReceive('getKeyType')->andReturn('int');
    $relation->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('relation.id', ['foreign.value', 'foreign.value.two']);
    $models = [new InstrumentBelongsToModelStub, new InstrumentBelongsToModelStub, new AnotherInstrumentBelongsToModelStub];
    $relation->addEagerConstraints($models);
});

test('ids in eager constraints can be zero', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();
    $relation->getRelated()->shouldReceive('getKeyName')->andReturn('id');
    $relation->getRelated()->shouldReceive('getKeyType')->andReturn('int');
    $relation->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('relation.id', [0, 'foreign.value']);
    $models = [new InstrumentBelongsToModelStub, new InstrumentBelongsToModelStubWithZeroId];
    $relation->addEagerConstraints($models);
});

test('ids in eager constraints can be backed enum', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();
    $relation->getRelated()->shouldReceive('getKeyName')->andReturn('id');
    $relation->getRelated()->shouldReceive('getKeyType')->andReturn('int');
    $relation->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('relation.id', [5, 'foreign.value']);
    $models = [new InstrumentBelongsToModelStub, new InstrumentBelongsToModelStubWithBackedEnumCast];
    $relation->addEagerConstraints($models);
});

test('relation is properly initialized', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();
    $model = m::mock(Model::class);
    $model->shouldReceive('setRelation')->once()->with('foo', null);
    $models = $relation->initRelation([$model], 'foo');

    $this->assertEquals([$model], $models);
});

test('models are properly matched to parents', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();

    $result1 = new class extends Model
    {
        protected $attributes = ['id' => 1];
    };

    $result2 = new class extends Model
    {
        protected $attributes = ['id' => 2];
    };

    $result3 = new class extends Model
    {
        protected $attributes = ['id' => 3];

        public function __toString()
        {
            return '3';
        }
    };

    $result4 = new class extends Model
    {
        protected $casts = [
            'id' => Bar::class,
        ];

        protected $attributes = ['id' => 5];
    };

    $model1 = new InstrumentBelongsToModelStub;
    $model1->foreign_key = 1;
    $model2 = new InstrumentBelongsToModelStub;
    $model2->foreign_key = 2;
    $model3 = new InstrumentBelongsToModelStub;
    $model3->foreign_key = new class
    {
        public function __toString()
        {
            return '3';
        }
    };
    $model4 = new InstrumentBelongsToModelStub;
    $model4->foreign_key = 5;
    $models = $relation->match(
        [$model1, $model2, $model3, $model4],
        new Collection([$result1, $result2, $result3, $result4]),
        'foo'
    );

    $this->assertEquals(1, $models[0]->foo->getAttribute('id'));
    $this->assertEquals(2, $models[1]->foo->getAttribute('id'));
    $this->assertSame('3', (string) $models[2]->foo->getAttribute('id'));
    $this->assertEquals(5, $models[3]->foo->getAttribute('id')->value);
});

test('associate method sets foreign key on model', function () {
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    [$relation, $builder, $related] = dbBelongsToGetRelation($parent);
    $associate = m::mock(Model::class);
    $associate->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
    $parent->shouldReceive('setAttribute')->once()->with('foreign_key', 1);
    $parent->shouldReceive('setRelation')->once()->with('relation', $associate);

    $relation->associate($associate);
});

test('dissociate method unsets foreign key on model', function () {
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    [$relation, $builder, $related] = dbBelongsToGetRelation($parent);
    $parent->shouldReceive('setAttribute')->once()->with('foreign_key', null);

    // Always set relation when we received Model
    $parent->shouldReceive('setRelation')->once()->with('relation', null);

    $relation->dissociate();
});

test('associate method sets foreign key on model by id', function () {
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    [$relation, $builder, $related] = dbBelongsToGetRelation($parent);
    $parent->shouldReceive('setAttribute')->once()->with('foreign_key', 1);

    // Always unset relation when we received id, regardless of dirtiness
    $parent->shouldReceive('isDirty')->never();
    $parent->shouldReceive('unsetRelation')->once()->with($relation->getRelationName());

    $relation->associate(1);
});

test('default eager constraints when incrementing', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();
    $relation->getRelated()->shouldReceive('getKeyName')->andReturn('id');
    $relation->getRelated()->shouldReceive('getKeyType')->andReturn('int');
    $relation->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('relation.id', m::mustBe([]));
    $models = [new MissingInstrumentBelongsToModelStub, new MissingInstrumentBelongsToModelStub];
    $relation->addEagerConstraints($models);
});

test('default eager constraints when incrementing and non int key type', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation(null, 'string');
    $relation->getQuery()->shouldReceive('whereIn')->once()->with('relation.id', m::mustBe([]));
    $models = [new MissingInstrumentBelongsToModelStub, new MissingInstrumentBelongsToModelStub];
    $relation->addEagerConstraints($models);
});

test('default eager constraints when not incrementing', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();
    $relation->getRelated()->shouldReceive('getKeyName')->andReturn('id');
    $relation->getRelated()->shouldReceive('getKeyType')->andReturn('int');
    $relation->getQuery()->shouldReceive('whereIntegerInRaw')->once()->with('relation.id', m::mustBe([]));
    $models = [new MissingInstrumentBelongsToModelStub, new MissingInstrumentBelongsToModelStub];
    $relation->addEagerConstraints($models);
});

test('is not null', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();

    $related->shouldReceive('getConnectionName')->never();

    $this->assertFalse($relation->is(null));
});

test('is model', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value');
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $this->assertTrue($relation->is($model));
});

test('is model with integer parent key', function () {
    $parent = m::mock(Model::class);

    // when addConstraints is called we need to return the foreign value
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    // when getParentKey is called we want to return an integer
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(1);

    [$relation, $builder, $related] = dbBelongsToGetRelation($parent);

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('1');
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $this->assertTrue($relation->is($model));
});

test('is model with integer related key', function () {
    $parent = m::mock(Model::class);

    // when addConstraints is called we need to return the foreign value
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    // when getParentKey is called we want to return a string
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('1');

    [$relation, $builder, $related] = dbBelongsToGetRelation($parent);

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $this->assertTrue($relation->is($model));
});

test('is model with integer keys', function () {
    $parent = m::mock(Model::class);

    // when addConstraints is called we need to return the foreign value
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    // when getParentKey is called we want to return an integer
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(1);

    [$relation, $builder, $related] = dbBelongsToGetRelation($parent);

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $this->assertTrue($relation->is($model));
});

test('is not model with null parent key', function () {
    $parent = m::mock(Model::class);

    // when addConstraints is called we need to return the foreign value
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    // when getParentKey is called we want to return null
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(null);

    [$relation, $builder, $related] = dbBelongsToGetRelation($parent);

    $related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value');
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    $this->assertFalse($relation->is($model));
});

test('is not model with null related key', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();

    $related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn(null);
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    $this->assertFalse($relation->is($model));
});

test('is not model with another key', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();

    $related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value.two');
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    $this->assertFalse($relation->is($model));
});

test('is not model with another table', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();

    $related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value');
    $model->shouldReceive('getTable')->once()->andReturn('table.two');
    $model->shouldReceive('getConnectionName')->never();

    $this->assertFalse($relation->is($model));
});

test('is not model with another connection', function () {
    [$relation, $builder, $related] = dbBelongsToGetRelation();

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value');
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation.two');

    $this->assertFalse($relation->is($model));
});

class InstrumentBelongsToModelStub extends Model
{
    public $foreign_key = 'foreign.value';
}

class AnotherInstrumentBelongsToModelStub extends Model
{
    public $foreign_key = 'foreign.value.two';
}

class InstrumentBelongsToModelStubWithZeroId extends Model
{
    public $foreign_key = 0;
}

class MissingInstrumentBelongsToModelStub extends Model
{
    public $foreign_key;
}

class InstrumentBelongsToModelStubWithBackedEnumCast extends Model
{
    protected $casts = [
        'foreign_key' => Bar::class,
    ];

    public $attributes = [
        'foreign_key' => 5,
    ];
}
