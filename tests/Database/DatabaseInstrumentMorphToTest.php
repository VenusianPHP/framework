<?php

namespace Tests\Database;

use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\MorphTo;
use Tests\Database\stubs\TestEnum;
use Mockery as m;

function dbMorphToGetRelationAssociate($parent)
{
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('where')->with('relation.id', '=', 'foreign.value');
    $related = m::mock(Model::class);
    $related->shouldReceive('getKey')->andReturn(1);
    $related->shouldReceive('getTable')->andReturn('relation');
    $related->shouldReceive('qualifyColumn')->andReturnUsing(fn (string $column) => "relation.{$column}");
    $builder->shouldReceive('getModel')->andReturn($related);

    return new MorphTo($builder, $parent, 'foreign_key', 'id', 'morph_type', 'relation');
}

function dbMorphToGetRelation($parent = null, $builder = null)
{
    $builder = $builder ?: m::mock(Builder::class);
    $builder->shouldReceive('where')->with('relation.id', '=', 'foreign.value');
    $related = m::mock(Model::class);
    $related->shouldReceive('getKeyName')->andReturn('id');
    $related->shouldReceive('getTable')->andReturn('relation');
    $related->shouldReceive('qualifyColumn')->andReturnUsing(fn (string $column) => "relation.{$column}");
    $builder->shouldReceive('getModel')->andReturn($related);
    $parent = $parent ?: new InstrumentMorphToModelStub;

    $relation = m::mock(MorphTo::class.'[createModelByType]', [$builder, $parent, 'foreign_key', 'id', 'morph_type', 'relation']);

    return [$relation, $builder, $related];
}

test('lookup dictionary is properly constructed for enums', function () {
    [$relation] = dbMorphToGetRelation();
    $relation->addEagerConstraints([
        $one = (object) ['morph_type' => 'morph_type_2', 'foreign_key' => TestEnum::test],
    ]);
    $dictionary = $relation->getDictionary();
    $relation->getDictionary();
    $enumKey = TestEnum::test;
    if (isset($enumKey->value)) {
        $value = $dictionary['morph_type_2'][$enumKey->value][0]->foreign_key;
        expect($value)->toEqual(TestEnum::test);
    } else {
        $this->fail('An enum should contain value property');
    }
});

test('lookup dictionary is properly constructed', function () {
    $stringish = new class
    {
        public function __toString()
        {
            return 'foreign_key_2';
        }
    };

    [$relation] = dbMorphToGetRelation();
    $relation->addEagerConstraints([
        $one = (object) ['morph_type' => 'morph_type_1', 'foreign_key' => 'foreign_key_1'],
        $two = (object) ['morph_type' => 'morph_type_1', 'foreign_key' => 'foreign_key_1'],
        $three = (object) ['morph_type' => 'morph_type_2', 'foreign_key' => 'foreign_key_2'],
        $four = (object) ['morph_type' => 'morph_type_2', 'foreign_key' => $stringish],
    ]);

    $dictionary = $relation->getDictionary();

    expect($dictionary)->toEqual([
        'morph_type_1' => [
            'foreign_key_1' => [
                $one,
                $two,
            ],
        ],
        'morph_type_2' => [
            'foreign_key_2' => [
                $three,
                $four,
            ],
        ],
    ]);
});

test('morph to with default', function () {
    [$relation, $builder] = dbMorphToGetRelation();
    $relation = $relation->withDefault();

    $builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentMorphToModelStub;

    expect($relation->getResults())->toEqual($newModel);
});

test('morph to with dynamic default', function () {
    [$relation, $builder] = dbMorphToGetRelation();
    $relation = $relation->withDefault(function ($newModel) {
        $newModel->username = 'taylor';
    });

    $builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentMorphToModelStub;
    $newModel->username = 'taylor';

    $result = $relation->getResults();

    expect($result)->toEqual($newModel)
        ->and($result->username)->toBe('taylor');
});

test('morph to with array default', function () {
    [$relation, $builder] = dbMorphToGetRelation();
    $relation = $relation->withDefault(['username' => 'taylor']);

    $builder->shouldReceive('first')->once()->andReturnNull();

    $newModel = new InstrumentMorphToModelStub;
    $newModel->username = 'taylor';

    $result = $relation->getResults();

    expect($result)->toEqual($newModel)
        ->and($result->username)->toBe('taylor');
});

test('morph to with zero morph type', function () {
    $parent = $this->getMockBuilder(InstrumentMorphToModelStub::class)->onlyMethods(['getAttributeFromArray', 'morphEagerTo', 'morphInstanceTo'])->getMock();
    $parent->method('getAttributeFromArray')->with('relation_type')->willReturn(0);
    $parent->expects($this->once())->method('morphInstanceTo');
    $parent->expects($this->never())->method('morphEagerTo');

    $parent->relation();
});

test('morph to with empty string morph type', function () {
    $parent = $this->getMockBuilder(InstrumentMorphToModelStub::class)->onlyMethods(['getAttributeFromArray', 'morphEagerTo', 'morphInstanceTo'])->getMock();
    $parent->method('getAttributeFromArray')->with('relation_type')->willReturn('');
    $parent->expects($this->once())->method('morphEagerTo');
    $parent->expects($this->never())->method('morphInstanceTo');

    $parent->relation();
});

test('morph to with specified class default', function () {
    $parent = new InstrumentMorphToModelStub;
    $parent->relation_type = InstrumentMorphToRelatedStub::class;

    $relation = $parent->relation()->withDefault();

    $newModel = new InstrumentMorphToRelatedStub;

    $result = $relation->getResults();

    expect($result)->toEqual($newModel);
});

test('associate method sets foreign key and type on model', function () {
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->with('foreign_key')->andReturn('foreign.value');

    $relation = dbMorphToGetRelationAssociate($parent);

    $associate = m::mock(Model::class);
    $associate->shouldReceive('getAttribute')->andReturn(1);
    $associate->shouldReceive('getMorphClass')->andReturn('Model');

    $parent->shouldReceive('setAttribute')->once()->with('foreign_key', 1);
    $parent->shouldReceive('setAttribute')->once()->with('morph_type', 'Model');
    $parent->shouldReceive('setRelation')->once()->with('relation', $associate);

    $relation->associate($associate);
});

test('associate method ignores null value', function () {
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');

    $relation = dbMorphToGetRelationAssociate($parent);

    $parent->shouldReceive('setAttribute')->once()->with('foreign_key', null);
    $parent->shouldReceive('setAttribute')->once()->with('morph_type', null);
    $parent->shouldReceive('setRelation')->once()->with('relation', null);

    $relation->associate(null);
});

test('dissociate method deletes unsets key and type on model', function () {
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');

    [$relation] = dbMorphToGetRelation($parent);

    $parent->shouldReceive('setAttribute')->once()->with('foreign_key', null);
    $parent->shouldReceive('setAttribute')->once()->with('morph_type', null);
    $parent->shouldReceive('setRelation')->once()->with('relation', null);

    $relation->dissociate();
});

test('is not null', function () {
    [$relation] = dbMorphToGetRelation();

    $relation->getRelated()->shouldReceive('getTable')->never();
    $relation->getRelated()->shouldReceive('getConnectionName')->never();

    expect($relation->is(null))->toBeFalse();
});

test('is model', function () {
    [$relation, $builder, $related] = dbMorphToGetRelation();

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value');
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation');

    expect($relation->is($model))->toBeTrue();
});

test('is model with integer parent key', function () {
    $parent = m::mock(Model::class);
    // when addConstraints is called we need to return the foreign value
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    // when getParentKey is called we want to return an integer
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(1);

    [$relation, $builder, $related] = dbMorphToGetRelation($parent);

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('1');
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation');

    expect($relation->is($model))->toBeTrue();
});

test('is model with integer related key', function () {
    $parent = m::mock(Model::class);
    // when addConstraints is called we need to return the foreign value
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    // when getParentKey is called we want to return a string
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('1');

    [$relation, $builder, $related] = dbMorphToGetRelation($parent);

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation');

    expect($relation->is($model))->toBeTrue();
});

test('is model with integer keys', function () {
    $parent = m::mock(Model::class);

    // when addConstraints is called we need to return the foreign value
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    // when getParentKey is called we want to return an integer
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(1);

    [$relation, $builder, $related] = dbMorphToGetRelation($parent);

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn(1);
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation');

    expect($relation->is($model))->toBeTrue();
});

test('is not model with null parent key', function () {
    $parent = m::mock(Model::class);

    // when addConstraints is called we need to return the foreign value
    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn('foreign.value');
    // when getParentKey is called we want to return null

    $parent->shouldReceive('getAttribute')->once()->with('foreign_key')->andReturn(null);

    [$relation, $builder, $related] = dbMorphToGetRelation($parent);

    $related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value');
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with null related key', function () {
    [$relation, $builder, $related] = dbMorphToGetRelation();

    $related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn(null);
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with another key', function () {
    [$relation, $builder, $related] = dbMorphToGetRelation();

    $related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value.two');
    $model->shouldReceive('getTable')->never();
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with another table', function () {
    [$relation, $builder, $related] = dbMorphToGetRelation();

    $related->shouldReceive('getConnectionName')->never();

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value');
    $model->shouldReceive('getTable')->once()->andReturn('table.two');
    $model->shouldReceive('getConnectionName')->never();

    expect($relation->is($model))->toBeFalse();
});

test('is not model with another connection', function () {
    [$relation, $builder, $related] = dbMorphToGetRelation();

    $related->shouldReceive('getConnectionName')->once()->andReturn('relation');

    $model = m::mock(Model::class);
    $model->shouldReceive('getAttribute')->once()->with('id')->andReturn('foreign.value');
    $model->shouldReceive('getTable')->once()->andReturn('relation');
    $model->shouldReceive('getConnectionName')->once()->andReturn('relation.two');

    expect($relation->is($model))->toBeFalse();
});

class InstrumentMorphToModelStub extends Model
{
    public $foreign_key = 'foreign.value';

    public $table = 'instrument_morph_to_model_stubs';

    public function relation()
    {
        return $this->morphTo();
    }
}

class InstrumentMorphToRelatedStub extends Model
{
    public $table = 'instrument_morph_to_related_stubs';
}
