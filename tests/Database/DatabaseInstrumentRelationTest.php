<?php

namespace Tests\Database;

use Exception;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Casts\Attribute;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\HasOne;
use Voyager\Database\Instrument\Relations\Relation;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mockery as m;

test('set relation fail', function () {
    $parent = new InstrumentRelationResetModelStub;
    $relation = new InstrumentRelationResetModelStub;
    $parent->setRelation('test', $relation);
    $parent->setRelation('foo', 'bar');
    $this->assertArrayNotHasKey('foo', $parent->toArray());
});

test('unset existing relation', function () {
    $parent = new InstrumentRelationResetModelStub;
    $relation = new InstrumentRelationResetModelStub;
    $parent->setRelation('foo', $relation);
    $parent->unsetRelation('foo');
    expect($parent->relationLoaded('foo'))->toBeFalse();
});

test('touch method updates related timestamps', function () {
    $builder = m::mock(Builder::class);
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $related = m::mock(InstrumentNoTouchingModelStub::class)->makePartial();
    $builder->shouldReceive('getModel')->andReturn($related);
    $builder->shouldReceive('whereNotNull');
    $builder->shouldReceive('where');
    $builder->shouldReceive('withoutGlobalScopes')->andReturn($builder);
    $relation = new HasOne($builder, $parent, 'foreign_key', 'id');
    $related->shouldReceive('getTable')->andReturn('table');
    $related->shouldReceive('getUpdatedAtColumn')->andReturn('updated_at');
    $now = Carbon::now();
    $related->shouldReceive('freshTimestampString')->andReturn($now);
    $builder->shouldReceive('update')->once()->with(['updated_at' => $now]);

    $relation->touch();
});

test('can disable parent touching for all models', function () {
    /** @var \Tests\Database\InstrumentNoTouchingModelStub $related */
    $related = m::mock(InstrumentNoTouchingModelStub::class)->makePartial();
    $related->shouldReceive('getUpdatedAtColumn')->never();
    $related->shouldReceive('freshTimestampString')->never();

    expect($related::isIgnoringTouch())->toBeFalse();

    Model::withoutTouching(function () use ($related) {
        expect($related::isIgnoringTouch())->toBeTrue();

        $builder = m::mock(Builder::class);
        $parent = m::mock(Model::class);

        $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $builder->shouldReceive('getModel')->andReturn($related);
        $builder->shouldReceive('whereNotNull');
        $builder->shouldReceive('where');
        $builder->shouldReceive('withoutGlobalScopes')->andReturn($builder);
        $relation = new HasOne($builder, $parent, 'foreign_key', 'id');
        $builder->shouldReceive('update')->never();

        $relation->touch();
    });

    expect($related::isIgnoringTouch())->toBeFalse();
});

test('can disable touching for specific model', function () {
    $related = m::mock(InstrumentNoTouchingModelStub::class)->makePartial();
    $related->shouldReceive('getUpdatedAtColumn')->never();
    $related->shouldReceive('freshTimestampString')->never();

    $anotherRelated = m::mock(InstrumentNoTouchingAnotherModelStub::class)->makePartial();

    expect($related::isIgnoringTouch())->toBeFalse()
        ->and($anotherRelated::isIgnoringTouch())->toBeFalse();

    InstrumentNoTouchingModelStub::withoutTouching(function () use ($related, $anotherRelated) {
        expect($related::isIgnoringTouch())->toBeTrue()
            ->and($anotherRelated::isIgnoringTouch())->toBeFalse();

        $builder = m::mock(Builder::class);
        $parent = m::mock(Model::class);

        $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $builder->shouldReceive('getModel')->andReturn($related);
        $builder->shouldReceive('whereNotNull');
        $builder->shouldReceive('where');
        $builder->shouldReceive('withoutGlobalScopes')->andReturnSelf();
        $relation = new HasOne($builder, $parent, 'foreign_key', 'id');
        $builder->shouldReceive('update')->never();

        $relation->touch();

        $anotherBuilder = m::mock(Builder::class);
        $anotherParent = m::mock(Model::class);

        $anotherParent->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $anotherBuilder->shouldReceive('getModel')->andReturn($anotherRelated);
        $anotherBuilder->shouldReceive('whereNotNull');
        $anotherBuilder->shouldReceive('where');
        $anotherBuilder->shouldReceive('withoutGlobalScopes')->andReturnSelf();
        $anotherRelation = new HasOne($anotherBuilder, $anotherParent, 'foreign_key', 'id');
        $now = Carbon::now();
        $anotherRelated->shouldReceive('freshTimestampString')->andReturn($now);
        $anotherBuilder->shouldReceive('update')->once()->with(['updated_at' => $now]);

        $anotherRelation->touch();
    });

    expect($related::isIgnoringTouch())->toBeFalse()
        ->and($anotherRelated::isIgnoringTouch())->toBeFalse();
});

test('parent model is not touched when child model is ignored', function () {
    $related = m::mock(InstrumentNoTouchingModelStub::class)->makePartial();
    $related->shouldReceive('getUpdatedAtColumn')->never();
    $related->shouldReceive('freshTimestampString')->never();

    $relatedChild = m::mock(InstrumentNoTouchingChildModelStub::class)->makePartial();
    $relatedChild->shouldReceive('getUpdatedAtColumn')->never();
    $relatedChild->shouldReceive('freshTimestampString')->never();

    expect($related::isIgnoringTouch())->toBeFalse()
        ->and($relatedChild::isIgnoringTouch())->toBeFalse();

    InstrumentNoTouchingModelStub::withoutTouching(function () use ($related, $relatedChild) {
        expect($related::isIgnoringTouch())->toBeTrue()
            ->and($relatedChild::isIgnoringTouch())->toBeTrue();

        $builder = m::mock(Builder::class);
        $parent = m::mock(Model::class);

        $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $builder->shouldReceive('getModel')->andReturn($related);
        $builder->shouldReceive('whereNotNull');
        $builder->shouldReceive('where');
        $builder->shouldReceive('withoutGlobalScopes')->andReturnSelf();
        $relation = new HasOne($builder, $parent, 'foreign_key', 'id');
        $builder->shouldReceive('update')->never();

        $relation->touch();

        $anotherBuilder = m::mock(Builder::class);
        $anotherParent = m::mock(Model::class);

        $anotherParent->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $anotherBuilder->shouldReceive('getModel')->andReturn($relatedChild);
        $anotherBuilder->shouldReceive('whereNotNull');
        $anotherBuilder->shouldReceive('where');
        $anotherBuilder->shouldReceive('withoutGlobalScopes')->andReturnSelf();
        $anotherRelation = new HasOne($anotherBuilder, $anotherParent, 'foreign_key', 'id');
        $anotherBuilder->shouldReceive('update')->never();

        $anotherRelation->touch();
    });

    expect($related::isIgnoringTouch())->toBeFalse()
        ->and($relatedChild::isIgnoringTouch())->toBeFalse();
});

test('ignored models state is reset when there are exceptions', function () {
    $related = m::mock(InstrumentNoTouchingModelStub::class)->makePartial();
    $related->shouldReceive('getUpdatedAtColumn')->never();
    $related->shouldReceive('freshTimestampString')->never();

    $relatedChild = m::mock(InstrumentNoTouchingChildModelStub::class)->makePartial();
    $relatedChild->shouldReceive('getUpdatedAtColumn')->never();
    $relatedChild->shouldReceive('freshTimestampString')->never();

    expect($related::isIgnoringTouch())->toBeFalse()
        ->and($relatedChild::isIgnoringTouch())->toBeFalse();

    try {
        InstrumentNoTouchingModelStub::withoutTouching(function () use ($related, $relatedChild) {
            expect($related::isIgnoringTouch())->toBeTrue()
                ->and($relatedChild::isIgnoringTouch())->toBeTrue();

            throw new Exception;
        });

        $this->fail('Exception was not thrown');
    } catch (Exception) {
        // Does nothing.
    }

    expect($related::isIgnoringTouch())->toBeFalse()
        ->and($relatedChild::isIgnoringTouch())->toBeFalse();
});

test('setting morph map with numeric array uses the table names', function () {
    Relation::morphMap([InstrumentRelationResetModelStub::class]);

    expect(Relation::morphMap())->toEqual([
        'reset' => InstrumentRelationResetModelStub::class,
    ]);

    Relation::morphMap([], false);
});

test('setting morph map with numeric keys', function () {
    Relation::morphMap([1 => 'App\User']);

    expect(Relation::morphMap())->toEqual([
        1 => 'App\User',
    ]);

    Relation::morphMap([], false);
});

test('get morph alias', function () {
    Relation::morphMap(['user' => 'App\User']);

    expect(Relation::getMorphAlias('App\User'))->toBe('user')
        ->and(Relation::getMorphAlias('Does\Not\Exist'))->toBe('Does\Not\Exist');
});

test('without relations', function () {
    $original = new InstrumentNoTouchingModelStub;

    $original->setRelation('foo', 'baz');

    expect($original->getRelation('foo'))->toBe('baz');

    $model = $original->withoutRelations();

    expect($model)->toBeInstanceOf(InstrumentNoTouchingModelStub::class)
        ->and($original->relationLoaded('foo'))->toBeTrue()
        ->and($model->relationLoaded('foo'))->toBeFalse();

    $model = $original->unsetRelations();

    expect($model)->toBeInstanceOf(InstrumentNoTouchingModelStub::class)
        ->and($original->relationLoaded('foo'))->toBeFalse()
        ->and($model->relationLoaded('foo'))->toBeFalse();
});

test('without relation', function () {
    $original = new InstrumentNoTouchingModelStub;

    $original->setRelation('foo', 'baz');
    $original->setRelation('bar', 'qux');

    $model = $original->withoutRelation('foo');

    expect($model)->toBeInstanceOf(InstrumentNoTouchingModelStub::class);
    $this->assertNotSame($model, $original);
    expect($original->relationLoaded('foo'))->toBeTrue()
        ->and($original->relationLoaded('bar'))->toBeTrue()
        ->and($model->relationLoaded('foo'))->toBeFalse()
        ->and($model->relationLoaded('bar'))->toBeTrue();
});

test('without relation with array', function () {
    $original = new InstrumentNoTouchingModelStub;

    $original->setRelation('foo', 'baz');
    $original->setRelation('bar', 'qux');
    $original->setRelation('bam', 'zap');

    $model = $original->withoutRelation(['foo', 'bar']);

    expect($original->relationLoaded('foo'))->toBeTrue()
        ->and($original->relationLoaded('bar'))->toBeTrue()
        ->and($original->relationLoaded('bam'))->toBeTrue()
        ->and($model->relationLoaded('foo'))->toBeFalse()
        ->and($model->relationLoaded('bar'))->toBeFalse()
        ->and($model->relationLoaded('bam'))->toBeTrue();
});

test('macroable', function () {
    Relation::macro('foo', function () {
        return 'foo';
    });

    $model = new InstrumentRelationResetModelStub;
    $relation = new InstrumentRelationStub($model->newQuery(), $model);

    $result = $relation->foo();
    expect($result)->toBe('foo');
});

test('is relation ignores attribute', function () {
    $model = new InstrumentRelationAndAttributeModelStub;

    expect($model->isRelation('parent'))->toBeTrue()
        ->and($model->isRelation('field'))->toBeFalse();
});

class InstrumentRelationResetModelStub extends Model
{
    protected $table = 'reset';

    // Override method call which would normally go through __call()

    public function getQuery()
    {
        return $this->newQuery()->getQuery();
    }
}

class InstrumentRelationStub extends Relation
{
    public function addConstraints()
    {
        //
    }

    public function addEagerConstraints(array $models)
    {
        //
    }

    public function initRelation(array $models, $relation)
    {
        //
    }

    public function match(array $models, Collection $results, $relation)
    {
        //
    }

    public function getResults()
    {
        //
    }
}

class InstrumentNoTouchingModelStub extends Model
{
    protected $table = 'table';
    protected $attributes = [
        'id' => 1,
    ];
}

class InstrumentNoTouchingChildModelStub extends InstrumentNoTouchingModelStub
{
    //
}

class InstrumentNoTouchingAnotherModelStub extends Model
{
    protected $table = 'another_table';
    protected $attributes = [
        'id' => 2,
    ];
}

class InstrumentRelationAndAttributeModelStub extends Model
{
    protected $table = 'one_more_table';

    public function field(): Attribute
    {
        return new Attribute(
            function ($value) {
                return $value;
            },
            function ($value) {
                return $value;
            },
        );
    }

    public function parent()
    {
        return $this->belongsTo(self::class);
    }
}
