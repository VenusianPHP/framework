<?php

use Voyager\Database\Connection;
use Voyager\Database\ConnectionResolverInterface;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\Pivot;
use Voyager\Database\Query\Grammars\Grammar;
use Voyager\Database\Query\Processors\Processor;
use Mockery as m;

test('properties are set correctly', function () {
    $parent = m::mock(Model::class.'[getConnectionName]');
    $parent->shouldReceive('getConnectionName')->twice()->andReturn('connection');
    $parent->setConnectionResolver($resolver = m::mock(ConnectionResolverInterface::class));
    $resolver->shouldReceive('connection')->andReturn($connection = m::mock(Connection::class));
    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar = m::mock(Grammar::class));
    $connection->shouldReceive('getPostProcessor')->andReturn($processor = m::mock(Processor::class));
    $parent->getConnection()->getQueryGrammar()->shouldReceive('getDateFormat')->andReturn('Y-m-d H:i:s');
    $parent->setDateFormat('Y-m-d H:i:s');
    $pivot = Pivot::fromAttributes($parent, ['foo' => 'bar', 'created_at' => '2015-09-12'], 'table', true);

    expect($pivot->getAttributes())->toEqual(['foo' => 'bar', 'created_at' => '2015-09-12 00:00:00'])
        ->and($pivot->getConnectionName())->toBe('connection')
        ->and($pivot->getTable())->toBe('table')
        ->and($pivot->exists)->toBeTrue()
        ->and($pivot->pivotParent)->toBe($parent);
});

test('mutators are called from constructor', function () {
    $parent = m::mock(Model::class.'[getConnectionName]');
    $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');

    $pivot = DatabaseInstrumentPivotTestMutatorStub::fromAttributes($parent, ['foo' => 'bar'], 'table', true);

    expect($pivot->getMutatorCalled())->toBeTrue();
});

test('from raw attributes does not double mutate', function () {
    $parent = m::mock(Model::class.'[getConnectionName]');
    $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');

    $pivot = DatabaseInstrumentPivotTestJsonCastStub::fromRawAttributes($parent, ['foo' => json_encode(['name' => 'Taylor'])], 'table', true);

    expect($pivot->foo)->toEqual(['name' => 'Taylor']);
});

test('from raw attributes does not mutate', function () {
    $parent = m::mock(Model::class.'[getConnectionName]');
    $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');

    $pivot = DatabaseInstrumentPivotTestMutatorStub::fromRawAttributes($parent, ['foo' => 'bar'], 'table', true);

    expect($pivot->getMutatorCalled())->toBeFalse();
});

test('properties unchanged are not dirty', function () {
    $parent = m::mock(Model::class.'[getConnectionName]');
    $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
    $pivot = Pivot::fromAttributes($parent, ['foo' => 'bar', 'shimy' => 'shake'], 'table', true);

    expect($pivot->getDirty())->toEqual([]);
});

test('properties changed are dirty', function () {
    $parent = m::mock(Model::class.'[getConnectionName]');
    $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
    $pivot = Pivot::fromAttributes($parent, ['foo' => 'bar', 'shimy' => 'shake'], 'table', true);
    $pivot->shimy = 'changed';

    expect($pivot->getDirty())->toEqual(['shimy' => 'changed']);
});

test('timestamp property is set if created at in attributes', function () {
    $parent = m::mock(Model::class.'[getConnectionName,getDates]');
    $parent->shouldReceive('getConnectionName')->andReturn('connection');
    $parent->shouldReceive('getDates')->andReturn([]);
    $pivot = DatabaseInstrumentPivotTestDateStub::fromAttributes($parent, ['foo' => 'bar', 'created_at' => 'foo'], 'table');
    expect($pivot->timestamps)->toBeTrue();

    $pivot = DatabaseInstrumentPivotTestDateStub::fromAttributes($parent, ['foo' => 'bar'], 'table');
    expect($pivot->timestamps)->toBeFalse();
});

test('timestamp property is true when creating from raw attributes', function () {
    $parent = m::mock(Model::class.'[getConnectionName,getDates]');
    $parent->shouldReceive('getConnectionName')->andReturn('connection');
    $pivot = Pivot::fromRawAttributes($parent, ['foo' => 'bar', 'created_at' => 'foo'], 'table');
    expect($pivot->timestamps)->toBeTrue();
});

test('keys can be set properly', function () {
    $parent = m::mock(Model::class.'[getConnectionName]');
    $parent->shouldReceive('getConnectionName')->once()->andReturn('connection');
    $pivot = Pivot::fromAttributes($parent, ['foo' => 'bar'], 'table');
    $pivot->setPivotKeys('foreign', 'other');

    expect($pivot->getForeignKey())->toBe('foreign')
        ->and($pivot->getOtherKey())->toBe('other');
});

test('delete method deletes model by keys', function () {
    $pivot = $this->getMockBuilder(Pivot::class)->onlyMethods(['newQueryWithoutRelationships'])->getMock();
    $pivot->setPivotKeys('foreign', 'other');
    $pivot->foreign = 'foreign.value';
    $pivot->other = 'other.value';
    $query = m::mock(stdClass::class);
    $query->shouldReceive('where')->once()->with(['foreign' => 'foreign.value', 'other' => 'other.value'])->andReturn($query);
    $query->shouldReceive('delete')->once()->andReturn(true);
    $pivot->expects($this->once())->method('newQueryWithoutRelationships')->willReturn($query);

    $rowsAffected = $pivot->delete();
    $this->assertEquals(1, $rowsAffected);
});

test('pivot model table name is singular', function () {
    $pivot = new Pivot;

    expect($pivot->getTable())->toBe('pivot');
});

test('pivot model with parent returns parents timestamp columns', function () {
    $parent = m::mock(Model::class);
    $parent->shouldReceive('getCreatedAtColumn')->andReturn('parent_created_at');
    $parent->shouldReceive('getUpdatedAtColumn')->andReturn('parent_updated_at');

    $pivotWithParent = new Pivot;
    $pivotWithParent->pivotParent = $parent;

    expect($pivotWithParent->getCreatedAtColumn())->toBe('parent_created_at')
        ->and($pivotWithParent->getUpdatedAtColumn())->toBe('parent_updated_at');
});

test('pivot model without parent returns model timestamp columns', function () {
    $model = new DummyModel;

    $pivotWithoutParent = new Pivot;

    expect($pivotWithoutParent->getCreatedAtColumn())->toEqual($model->getCreatedAtColumn())
        ->and($pivotWithoutParent->getUpdatedAtColumn())->toEqual($model->getUpdatedAtColumn());
});

test('without relations', function () {
    $original = new Pivot;

    $original->pivotParent = 'foo';
    $original->setRelation('bar', 'baz');

    expect($original->getRelation('bar'))->toBe('baz');

    $pivot = $original->withoutRelations();

    expect($pivot)->toBeInstanceOf(Pivot::class)
        ->and($pivot)->not->toBe($original)
        ->and($original->pivotParent)->toBe('foo')
        ->and($pivot->pivotParent)->toBeNull()
        ->and($original->relationLoaded('bar'))->toBeTrue()
        ->and($pivot->relationLoaded('bar'))->toBeFalse();

    $pivot = $original->unsetRelations();

    expect($pivot)->toBe($original)
        ->and($pivot->pivotParent)->toBeNull()
        ->and($pivot->relationLoaded('bar'))->toBeFalse();
});

class DatabaseInstrumentPivotTestDateStub extends Pivot
{
    public function getDates()
    {
        return [];
    }
}

class DatabaseInstrumentPivotTestMutatorStub extends Pivot
{
    private $mutatorCalled = false;

    public function setFooAttribute($value)
    {
        $this->mutatorCalled = true;

        return $value;
    }

    public function getMutatorCalled()
    {
        return $this->mutatorCalled;
    }
}

class DatabaseInstrumentPivotTestJsonCastStub extends Pivot
{
    protected $casts = [
        'foo' => 'json',
    ];
}

class DummyModel extends Model
{
    //
}
