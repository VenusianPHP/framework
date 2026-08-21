<?php

namespace Tests\Database;

use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\RelationNotFoundException;
use Voyager\Database\Instrument\Relations\BelongsTo;
use Voyager\Database\Instrument\Relations\Concerns\SupportsInverseRelations;
use Voyager\Database\Instrument\Relations\Relation;
use Voyager\NutsAndBolts\DataObjects\Stringable;
use Mockery as m;

test('builder callback is not applied when inverse relation is not set', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());
    $builder->shouldReceive('afterQuery')->never();

    new HasInverseRelationStub($builder, new HasInverseRelationParentStub());
});

test('builder callback is not set if inverse relation is empty string', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());
    $builder->shouldReceive('afterQuery')->never();

    (new HasInverseRelationStub($builder, new HasInverseRelationParentStub()))->inverse('');
})->throws(RelationNotFoundException::class);

test('builder callback is not set if inverse relationship does not exist', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());
    $builder->shouldReceive('afterQuery')->never();

    (new HasInverseRelationStub($builder, new HasInverseRelationParentStub()))->inverse('foo');
})->throws(RelationNotFoundException::class);

test('without inverse method removes inverse relation', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());
    $builder->shouldReceive('afterQuery')->once()->andReturnSelf();

    $relation = (new HasInverseRelationStub($builder, new HasInverseRelationParentStub()));
    $this->assertNull($relation->getInverseRelationship());

    $relation->inverse('test');
    $this->assertSame('test', $relation->getInverseRelationship());

    $relation->withoutInverse();
    $this->assertNull($relation->getInverseRelationship());
});

test('builder callback is applied when inverse relation is set', function () {
    $parent = new HasInverseRelationParentStub();

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());
    $builder->shouldReceive('afterQuery')->withArgs(function (\Closure $callback) use ($parent) {
        $relation = (new \ReflectionFunction($callback))->getClosureThis();

        return $relation instanceof HasInverseRelationStub && $relation->getParent() === $parent;
    })->once()->andReturnSelf();

    (new HasInverseRelationStub($builder, $parent))->inverse('test');
});

test('builder callback applies inverse relation to all models in result', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());

    // Capture the callback so that we can manually call it.
    $afterQuery = null;
    $builder->shouldReceive('afterQuery')->withArgs(function (\Closure $callback) use (&$afterQuery) {
        return (bool) $afterQuery = $callback;
    })->once()->andReturnSelf();

    $parent = new HasInverseRelationParentStub();
    (new HasInverseRelationStub($builder, $parent))->inverse('test');

    $results = new Collection(array_fill(0, 5, new HasInverseRelationRelatedStub()));

    foreach ($results as $model) {
        $this->assertEmpty($model->getRelations());
        $this->assertFalse($model->relationLoaded('test'));
    }

    $results = $afterQuery($results);

    foreach ($results as $model) {
        $this->assertNotEmpty($model->getRelations());
        $this->assertTrue($model->relationLoaded('test'));
        $this->assertSame($parent, $model->test);
    }
});

test('inverse relation is not set if inverse relation is unset', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationRelatedStub());

    // Capture the callback so that we can manually call it.
    $afterQuery = null;
    $builder->shouldReceive('afterQuery')->withArgs(function (\Closure $callback) use (&$afterQuery) {
        return (bool) $afterQuery = $callback;
    })->once()->andReturnSelf();

    $parent = new HasInverseRelationParentStub();
    $relation = (new HasInverseRelationStub($builder, $parent));
    $relation->inverse('test');

    $results = new Collection(array_fill(0, 5, new HasInverseRelationRelatedStub()));
    foreach ($results as $model) {
        $this->assertEmpty($model->getRelations());
    }
    $results = $afterQuery($results);
    foreach ($results as $model) {
        $this->assertNotEmpty($model->getRelations());
        $this->assertSame($parent, $model->getRelation('test'));
    }

    // Reset the inverse relation
    $relation->withoutInverse();

    $results = new Collection(array_fill(0, 5, new HasInverseRelationRelatedStub()));
    foreach ($results as $model) {
        $this->assertEmpty($model->getRelations());
    }
    foreach ($results as $model) {
        $this->assertEmpty($model->getRelations());
    }
});

test('provides possible inverse relation based on parent', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasOneInverseChildModel);

    $relation = (new HasInverseRelationStub($builder, new HasInverseRelationParentStub));

    $possibleRelations = ['hasInverseRelationParentStub', 'parentStub', 'owner'];
    $this->assertSame($possibleRelations, array_values($relation->exposeGetPossibleInverseRelations()));
});

test('provides possible inverse relation based on foreign key', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationParentStub);

    $relation = (new HasInverseRelationStub($builder, new HasInverseRelationParentStub, 'test_id'));

    $this->assertTrue(in_array('test', $relation->exposeGetPossibleInverseRelations()));
});

test('provides possible recursive relations if related is the same class as parent', function () {
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn(new HasInverseRelationParentStub);

    $relation = (new HasInverseRelationStub($builder, new HasInverseRelationParentStub));

    $this->assertTrue(in_array('parent', $relation->exposeGetPossibleInverseRelations()));
});

test('guesses inverse relation based on parent', function ($guessedRelation) {
    $related = m::mock(Model::class);
    $related->shouldReceive('isRelation')->andReturnUsing(fn ($relation) => $relation === $guessedRelation);

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn($related);

    $relation = (new HasInverseRelationStub($builder, new HasInverseRelationParentStub));

    $this->assertSame($guessedRelation, $relation->exposeGuessInverseRelation());
})->with(['hasInverseRelationParentStub', 'parentStub', 'owner']);

test('guesses possible inverse relation based on foreign key', function () {
    $related = m::mock(Model::class);
    $related->shouldReceive('isRelation')->andReturnUsing(fn ($relation) => $relation === 'test');

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn($related);

    $relation = (new HasInverseRelationStub($builder, new HasInverseRelationParentStub, 'test_id'));

    $this->assertSame('test', $relation->exposeGuessInverseRelation());
});

test('guesses recursive inverse relations if related is same class as parent', function () {
    $related = m::mock(Model::class);
    $related->shouldReceive('isRelation')->andReturnUsing(fn ($relation) => $relation === 'parent');

    $parent = clone $related;
    $parent->shouldReceive('getForeignKey')->andReturn('recursive_parent_id');
    $parent->shouldReceive('getKeyName')->andReturn('id');

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn($related);

    $relation = (new HasInverseRelationStub($builder, $parent));

    $this->assertSame('parent', $relation->exposeGuessInverseRelation());
});

test('sets guessed inverse relation based on parent', function ($guessedRelation) {
    $related = m::mock(Model::class);
    $related->shouldReceive('isRelation')->andReturnUsing(fn ($relation) => $relation === $guessedRelation);

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn($related);
    $builder->shouldReceive('afterQuery')->once()->andReturnSelf();

    $relation = (new HasInverseRelationStub($builder, new HasInverseRelationParentStub))->inverse();

    $this->assertSame($guessedRelation, $relation->getInverseRelationship());
})->with(['hasInverseRelationParentStub', 'parentStub', 'owner']);

test('sets recursive inverse relations if related is same class as parent', function () {
    $related = m::mock(Model::class);
    $related->shouldReceive('isRelation')->andReturnUsing(fn ($relation) => $relation === 'parent');

    $parent = clone $related;
    $parent->shouldReceive('getForeignKey')->andReturn('recursive_parent_id');
    $parent->shouldReceive('getKeyName')->andReturn('id');

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn($related);
    $builder->shouldReceive('afterQuery')->once()->andReturnSelf();

    $relation = (new HasInverseRelationStub($builder, $parent))->inverse();

    $this->assertSame('parent', $relation->getInverseRelationship());
});

test('sets guessed inverse relation based on foreign key', function () {
    $related = m::mock(Model::class);
    $related->shouldReceive('isRelation')->andReturnUsing(fn ($relation) => $relation === 'test');

    $builder = m::mock(Builder::class);
    $builder->shouldReceive('getModel')->andReturn($related);
    $builder->shouldReceive('afterQuery')->once()->andReturnSelf();

    $relation = (new HasInverseRelationStub($builder, new HasInverseRelationParentStub, 'test_id'))->inverse();

    $this->assertSame('test', $relation->getInverseRelationship());
});

test('only hydrates inverse relation on models', function () {
    $relation = m::mock(HasInverseRelationStub::class)->shouldAllowMockingProtectedMethods()->makePartial();
    $relation->shouldReceive('getParent')->andReturn(new HasInverseRelationParentStub);
    $relation->shouldReceive('applyInverseRelationToModel')->times(6);
    $relation->exposeApplyInverseRelationToCollection([
        new HasInverseRelationRelatedStub(),
        12345,
        new HasInverseRelationRelatedStub(),
        new HasInverseRelationRelatedStub(),
        Model::class,
        new HasInverseRelationRelatedStub(),
        true,
        [],
        new HasInverseRelationRelatedStub(),
        'foo',
        new class() {
        },
        new HasInverseRelationRelatedStub(),
    ]);
});

class HasInverseRelationParentStub extends Model
{
    protected static $unguarded = true;
    protected $primaryKey = 'id';

    public function getForeignKey()
    {
        return 'parent_stub_id';
    }
}

class HasInverseRelationRelatedStub extends Model
{
    protected static $unguarded = true;
    protected $primaryKey = 'id';

    public function getForeignKey()
    {
        return 'child_stub_id';
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(HasInverseRelationParentStub::class);
    }
}

class HasInverseRelationStub extends Relation
{
    use SupportsInverseRelations;

    public function __construct(
        Builder $query,
        Model $parent,
        protected ?string $foreignKey = null,
    ) {
        parent::__construct($query, $parent);
        $this->foreignKey ??= (new Stringable(class_basename($parent)))->snake()->finish('_id')->toString();
    }

    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    // None of these methods will actually be called - they're just needed to fill out `Relation`
    public function match(array $models, Collection $results, $relation)
    {
        return $models;
    }

    public function initRelation(array $models, $relation)
    {
        return $models;
    }

    public function getResults()
    {
        return $this->query->get();
    }

    public function addConstraints()
    {
        //
    }

    public function addEagerConstraints(array $models)
    {
        //
    }

    // Expose access to protected methods for testing
    public function exposeGetPossibleInverseRelations(): array
    {
        return $this->getPossibleInverseRelations();
    }

    public function exposeGuessInverseRelation(): ?string
    {
        return $this->guessInverseRelation();
    }

    public function exposeApplyInverseRelationToCollection($models, ?Model $parent = null)
    {
        return $this->applyInverseRelationToCollection($models, $parent);
    }
}
