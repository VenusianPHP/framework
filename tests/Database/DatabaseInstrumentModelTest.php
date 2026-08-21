<?php

namespace Tests\Database;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Foo\Bar\InstrumentModelNamespacedStub;
use Voyager\Contracts\Database\Instrument\Castable;
use Voyager\Contracts\Database\Instrument\CastsAttributes;
use Voyager\Contracts\Database\Instrument\CastsInboundAttributes;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Database\Connection;
use Voyager\Database\ConnectionResolverInterface;
use Voyager\Database\ConnectionResolverInterface as Resolver;
use Voyager\Database\Instrument\Attributes\CollectedBy;
use Voyager\Database\Instrument\Attributes\ObservedBy;
use Voyager\Database\Instrument\Attributes\UseFactory;
use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Casts\ArrayObject;
use Voyager\Database\Instrument\Casts\AsArrayObject;
use Voyager\Database\Instrument\Casts\AsCollection;
use Voyager\Database\Instrument\Casts\AsEncryptedArrayObject;
use Voyager\Database\Instrument\Casts\AsEncryptedCollection;
use Voyager\Database\Instrument\Casts\AsEnumArrayObject;
use Voyager\Database\Instrument\Casts\AsEnumCollection;
use Voyager\Database\Instrument\Casts\AsFluent;
use Voyager\Database\Instrument\Casts\AsHtmlString;
use Voyager\Database\Instrument\Casts\AsStringable;
use Voyager\Database\Instrument\Casts\AsUri;
use Voyager\Database\Instrument\Casts\Attribute;
use Voyager\Database\Instrument\Collection;
use Voyager\Database\Instrument\Concerns\HasUlids;
use Voyager\Database\Instrument\Concerns\HasUuids;
use Voyager\Database\Instrument\Factories\Factory;
use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\JsonEncodingException;
use Voyager\Database\Instrument\MassAssignmentException;
use Voyager\Database\Instrument\MissingAttributeException;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\BelongsTo;
use Voyager\Database\Instrument\Relations\Relation;
use Voyager\Database\Query\Builder as BaseBuilder;
use Voyager\Database\Query\Grammars\Grammar;
use Voyager\Database\Query\Processors\Processor;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\Collection as BaseCollection;
use Voyager\NutsAndBolts\Fluent;
use Voyager\NutsAndBolts\HtmlString;
use Voyager\NutsAndBolts\Concerns\InteractsWithTime;
use Voyager\NutsAndBolts\DataObjects\Stringable;
use Voyager\NutsAndBolts\Uri;
use Tests\Database\stubs\TestCast;
use Tests\Database\stubs\TestValueObject;
use InvalidArgumentException;
use LogicException;
use Mockery as m;
use ReflectionClass;
use stdClass;
use Stringable as NativeStringable;

include_once 'Enums.php';

uses(InteractsWithTime::class);

function modelAddMockConnection($model)
{
    $model->setConnectionResolver($resolver = m::mock(ConnectionResolverInterface::class));
    $resolver->shouldReceive('connection')->andReturn($connection = m::mock(Connection::class));
    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar = m::mock(Grammar::class));
    $grammar->shouldReceive('getBitwiseOperators')->andReturn([]);
    $grammar->shouldReceive('isExpression')->andReturnFalse();
    $connection->shouldReceive('getPostProcessor')->andReturn($processor = m::mock(Processor::class));
    $connection->shouldReceive('query')->andReturnUsing(function () use ($connection, $grammar, $processor) {
        return new BaseBuilder($connection, $grammar, $processor);
    });
}

afterEach(function () {
    Carbon::setTestNow(null);

    Model::unsetEventDispatcher();
    Carbon::resetToStringFormat();
});

test('attribute manipulation', function () {
    $model = new InstrumentModelStub;
    $model->name = 'foo';
    $this->assertSame('foo', $model->name);
    $this->assertTrue(isset($model->name));
    unset($model->name);
    $this->assertFalse(isset($model->name));

    // test mutation
    $model->list_items = ['name' => 'taylor'];
    $this->assertEquals(['name' => 'taylor'], $model->list_items);
    $attributes = $model->getAttributes();
    $this->assertSame(json_encode(['name' => 'taylor']), $attributes['list_items']);
});

test('set attribute with numeric key', function () {
    $model = new InstrumentDateModelStub;
    $model->setAttribute(0, 'value');

    $this->assertEquals([0 => 'value'], $model->getAttributes());
});

test('dirty attributes', function () {
    $model = new InstrumentModelStub(['foo' => '1', 'bar' => 2, 'baz' => 3]);
    $model->syncOriginal();
    $model->foo = 1;
    $model->bar = 20;
    $model->baz = 30;

    $this->assertTrue($model->isDirty());
    $this->assertFalse($model->isDirty('foo'));
    $this->assertTrue($model->isDirty('bar'));
    $this->assertTrue($model->isDirty('foo', 'bar'));
    $this->assertTrue($model->isDirty(['foo', 'bar']));
});

test('int and null comparison when dirty', function () {
    $model = new InstrumentModelCastingStub;
    $model->intAttribute = null;
    $model->syncOriginal();
    $this->assertFalse($model->isDirty('intAttribute'));
    $model->forceFill(['intAttribute' => 0]);
    $this->assertTrue($model->isDirty('intAttribute'));
});

test('float and null comparison when dirty', function () {
    $model = new InstrumentModelCastingStub;
    $model->floatAttribute = null;
    $model->syncOriginal();
    $this->assertFalse($model->isDirty('floatAttribute'));
    $model->forceFill(['floatAttribute' => 0.0]);
    $this->assertTrue($model->isDirty('floatAttribute'));
});

test('dirty on cast or date attributes', function () {
    $model = new InstrumentModelCastingStub;
    $model->setDateFormat('Y-m-d H:i:s');
    $model->boolAttribute = 1;
    $model->foo = 1;
    $model->bar = '2017-03-18';
    $model->dateAttribute = '2017-03-18';
    $model->datetimeAttribute = '2017-03-23 22:17:00';
    $model->syncOriginal();

    $model->boolAttribute = true;
    $model->foo = true;
    $model->bar = '2017-03-18 00:00:00';
    $model->dateAttribute = '2017-03-18 00:00:00';
    $model->datetimeAttribute = null;

    $this->assertTrue($model->isDirty());
    $this->assertTrue($model->isDirty('foo'));
    $this->assertTrue($model->isDirty('bar'));
    $this->assertFalse($model->isDirty('boolAttribute'));
    $this->assertFalse($model->isDirty('dateAttribute'));
    $this->assertTrue($model->isDirty('datetimeAttribute'));
});

test('dirty on casted objects', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'objectAttribute' => '["one", "two", "three"]',
        'collectionAttribute' => '["one", "two", "three"]',
    ]);
    $model->syncOriginal();

    $model->objectAttribute = ['one', 'two', 'three'];
    $model->collectionAttribute = ['one', 'two', 'three'];

    $this->assertFalse($model->isDirty());
    $this->assertFalse($model->isDirty('objectAttribute'));
    $this->assertFalse($model->isDirty('collectionAttribute'));
});

test('dirty on casted array object', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asarrayobjectAttribute' => '{"foo": "bar"}',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(ArrayObject::class, $model->asarrayobjectAttribute);
    $this->assertFalse($model->isDirty('asarrayobjectAttribute'));

    $model->asarrayobjectAttribute = ['foo' => 'bar'];
    $this->assertFalse($model->isDirty('asarrayobjectAttribute'));

    $model->asarrayobjectAttribute = ['foo' => 'baz'];
    $this->assertTrue($model->isDirty('asarrayobjectAttribute'));
});

test('dirty on casted collection', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'ascollectionAttribute' => '{"foo": "bar"}',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(BaseCollection::class, $model->ascollectionAttribute);
    $this->assertFalse($model->isDirty('ascollectionAttribute'));

    $model->ascollectionAttribute = ['foo' => 'bar'];
    $this->assertFalse($model->isDirty('ascollectionAttribute'));

    $model->ascollectionAttribute = ['foo' => 'baz'];
    $this->assertTrue($model->isDirty('ascollectionAttribute'));
});

test('dirty on casted custom collection', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asCustomCollectionAttribute' => '{"bar": "foo"}',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(CustomCollection::class, $model->asCustomCollectionAttribute);
    $this->assertFalse($model->isDirty('asCustomCollectionAttribute'));

    $model->asCustomCollectionAttribute = ['bar' => 'foo'];
    $this->assertFalse($model->isDirty('asCustomCollectionAttribute'));

    $model->asCustomCollectionAttribute = ['baz' => 'foo'];
    $this->assertTrue($model->isDirty('asCustomCollectionAttribute'));
});

test('dirty on casted custom collection as array', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asCustomCollectionAsArrayAttribute' => '{"bar": "foo"}',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(CustomCollection::class, $model->asCustomCollectionAsArrayAttribute);
    $this->assertFalse($model->isDirty('asCustomCollectionAsArrayAttribute'));

    $model->asCustomCollectionAsArrayAttribute = ['bar' => 'foo'];
    $this->assertFalse($model->isDirty('asCustomCollectionAsArrayAttribute'));

    $model->asCustomCollectionAsArrayAttribute = ['baz' => 'foo'];
    $this->assertTrue($model->isDirty('asCustomCollectionAsArrayAttribute'));
});

test('dirty on casted stringable', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asStringableAttribute' => 'foo bar',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(Stringable::class, $model->asStringableAttribute);
    $this->assertFalse($model->isDirty('asStringableAttribute'));

    $model->asStringableAttribute = new Stringable('foo bar');
    $this->assertFalse($model->isDirty('asStringableAttribute'));

    $model->asStringableAttribute = new Stringable('foo baz');
    $this->assertTrue($model->isDirty('asStringableAttribute'));
});

test('dirty on casted html string', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asHtmlStringAttribute' => '<div>foo bar</div>',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(HtmlString::class, $model->asHtmlStringAttribute);
    $this->assertFalse($model->isDirty('asHtmlStringAttribute'));

    $model->asHtmlStringAttribute = new HtmlString('<div>foo bar</div>');
    $this->assertFalse($model->isDirty('asHtmlStringAttribute'));

    $model->asHtmlStringAttribute = new Stringable('<div>foo baz</div>');
    $this->assertTrue($model->isDirty('asHtmlStringAttribute'));
});

test('dirty on casted uri', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asUriAttribute' => 'https://www.example.com:1234?query=param&another=value',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(Uri::class, $model->asUriAttribute);
    $this->assertFalse($model->isDirty('asUriAttribute'));

    $model->asUriAttribute = new Uri('https://www.example.com:1234?query=param&another=value');
    $this->assertFalse($model->isDirty('asUriAttribute'));

    $model->asUriAttribute = new Uri('https://www.updated.com:1234?query=param&another=value');
    $this->assertTrue($model->isDirty('asUriAttribute'));
});

test('dirty on casted fluent', function () {
    $value = [
        'address' => [
            'street' => 'test_street',
            'city' => 'test_city',
        ],
    ];

    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes(['asFluentAttribute' => json_encode($value)]);
    $model->syncOriginal();

    $this->assertInstanceOf(Fluent::class, $model->asFluentAttribute);
    $this->assertFalse($model->isDirty('asFluentAttribute'));

    $model->asFluentAttribute = new Fluent($value);
    $this->assertFalse($model->isDirty('asFluentAttribute'));

    $value['address']['street'] = 'updated_street';
    $model->asFluentAttribute = new Fluent($value);
    $this->assertTrue($model->isDirty('asFluentAttribute'));
});

test('dirty on enum collection object', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asEnumCollectionAttribute' => '["draft", "pending"]',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(BaseCollection::class, $model->asEnumCollectionAttribute);
    $this->assertFalse($model->isDirty('asEnumCollectionAttribute'));

    $model->asEnumCollectionAttribute = ['draft', 'pending'];
    $this->assertFalse($model->isDirty('asEnumCollectionAttribute'));

    $model->asEnumCollectionAttribute = ['draft', 'done'];
    $this->assertTrue($model->isDirty('asEnumCollectionAttribute'));
});

test('dirty on custom enum collection object', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asCustomEnumCollectionAttribute' => '["draft", "pending"]',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(BaseCollection::class, $model->asCustomEnumCollectionAttribute);
    $this->assertFalse($model->isDirty('asCustomEnumCollectionAttribute'));

    $model->asCustomEnumCollectionAttribute = ['draft', 'pending'];
    $this->assertFalse($model->isDirty('asCustomEnumCollectionAttribute'));

    $model->asCustomEnumCollectionAttribute = ['draft', 'done'];
    $this->assertTrue($model->isDirty('asCustomEnumCollectionAttribute'));
});

test('dirty on enum array object', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asEnumArrayObjectAttribute' => '["draft", "pending"]',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(ArrayObject::class, $model->asEnumArrayObjectAttribute);
    $this->assertFalse($model->isDirty('asEnumArrayObjectAttribute'));

    $model->asEnumArrayObjectAttribute = ['draft', 'pending'];
    $this->assertFalse($model->isDirty('asEnumArrayObjectAttribute'));

    $model->asEnumArrayObjectAttribute = ['draft', 'done'];
    $this->assertTrue($model->isDirty('asEnumArrayObjectAttribute'));
});

test('dirty on custom enum array object using', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'asCustomEnumArrayObjectAttribute' => '["draft", "pending"]',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(ArrayObject::class, $model->asCustomEnumArrayObjectAttribute);
    $this->assertFalse($model->isDirty('asCustomEnumArrayObjectAttribute'));

    $model->asCustomEnumArrayObjectAttribute = ['draft', 'pending'];
    $this->assertFalse($model->isDirty('asCustomEnumArrayObjectAttribute'));

    $model->asCustomEnumArrayObjectAttribute = ['draft', 'done'];
    $this->assertTrue($model->isDirty('asCustomEnumArrayObjectAttribute'));
});

test('has casts on enum attribute', function () {
    $model = new InstrumentModelEnumCastingStub();
    $this->assertTrue($model->hasCast('enumAttribute', StringStatus::class));
});

test('clean attributes', function () {
    $model = new InstrumentModelStub(['foo' => '1', 'bar' => 2, 'baz' => 3]);
    $model->syncOriginal();
    $model->foo = 1;
    $model->bar = 20;
    $model->baz = 30;

    $this->assertFalse($model->isClean());
    $this->assertTrue($model->isClean('foo'));
    $this->assertFalse($model->isClean('bar'));
    $this->assertFalse($model->isClean('foo', 'bar'));
    $this->assertFalse($model->isClean(['foo', 'bar']));
});

test('clean when float update attribute', function () {
    // test is equivalent
    $model = new InstrumentModelStub(['castedFloat' => 8 - 6.4]);
    $model->syncOriginal();
    $model->castedFloat = 1.6;
    $this->assertTrue($model->originalIsEquivalent('castedFloat'));

    // test is not equivalent
    $model = new InstrumentModelStub(['castedFloat' => 5.6]);
    $model->syncOriginal();
    $model->castedFloat = 5.5;
    $this->assertFalse($model->originalIsEquivalent('castedFloat'));
});

test('calculated attributes', function () {
    $model = new InstrumentModelStub;
    $model->password = 'secret';
    $attributes = $model->getAttributes();

    // ensure password attribute was not set to null
    $this->assertArrayNotHasKey('password', $attributes);
    $this->assertSame('******', $model->password);

    $hash = 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4';

    $this->assertEquals($hash, $attributes['password_hash']);
    $this->assertEquals($hash, $model->password_hash);
});

test('array access to attributes', function () {
    $model = new InstrumentModelStub(['attributes' => 1, 'connection' => 2, 'table' => 3]);
    unset($model['table']);

    $this->assertTrue(isset($model['attributes']));
    $this->assertEquals(1, $model['attributes']);
    $this->assertTrue(isset($model['connection']));
    $this->assertEquals(2, $model['connection']);
    $this->assertFalse(isset($model['table']));
    $this->assertEquals(null, $model['table']);
    $this->assertFalse(isset($model['with']));
});

test('only', function () {
    $model = new InstrumentModelStub;
    $model->first_name = 'taylor';
    $model->last_name = 'otwell';
    $model->project = 'laravel';

    $this->assertEquals(['project' => 'laravel'], $model->only('project'));
    $this->assertEquals(['first_name' => 'taylor', 'last_name' => 'otwell'], $model->only('first_name', 'last_name'));
    $this->assertEquals(['first_name' => 'taylor', 'last_name' => 'otwell'], $model->only(['first_name', 'last_name']));
});

test('except', function () {
    $model = new InstrumentModelStub;
    $model->first_name = 'taylor';
    $model->last_name = 'otwell';
    $model->project = 'laravel';

    $this->assertEquals(['first_name' => 'taylor', 'last_name' => 'otwell'], $model->except('project'));
    $this->assertEquals(['project' => 'laravel'], $model->except('first_name', 'last_name'));
    $this->assertEquals(['project' => 'laravel'], $model->except(['first_name', 'last_name']));
});

test('new instance returns new instance with attributes set', function () {
    $model = new InstrumentModelStub;
    $instance = $model->newInstance(['name' => 'taylor']);
    $this->assertInstanceOf(InstrumentModelStub::class, $instance);
    $this->assertSame('taylor', $instance->name);
});

test('new instance returns new instance with table set', function () {
    $model = new InstrumentModelStub;
    $model->setTable('test');
    $newInstance = $model->newInstance();

    $this->assertSame('test', $newInstance->getTable());
});

test('new instance returns new instance with merged casts', function () {
    $model = new InstrumentModelStub;
    $model->mergeCasts(['foo' => 'date']);
    $newInstance = $model->newInstance();

    $this->assertArrayHasKey('foo', $newInstance->getCasts());
    $this->assertSame('date', $newInstance->getCasts()['foo']);
});

test('create method saves new model', function () {
    $_SERVER['__instrument.saved'] = false;
    $model = InstrumentModelSaveStub::create(['name' => 'taylor']);
    $this->assertTrue($_SERVER['__instrument.saved']);
    $this->assertSame('taylor', $model->name);
});

test('make method does not save new model', function () {
    $_SERVER['__instrument.saved'] = false;
    $model = InstrumentModelSaveStub::make(['name' => 'taylor']);
    $this->assertFalse($_SERVER['__instrument.saved']);
    $this->assertSame('taylor', $model->name);
});

test('force create method saves new model with guarded attributes', function () {
    $_SERVER['__instrument.saved'] = false;
    $model = InstrumentModelSaveStub::forceCreate(['id' => 21]);
    $this->assertTrue($_SERVER['__instrument.saved']);
    $this->assertEquals(21, $model->id);
});

test('find method use write pdo', function () {
    InstrumentModelFindWithWritePdoStub::onWriteConnection()->find(1);
});

test('destroy method calls query builder correctly', function () {
    InstrumentModelDestroyStub::destroy(1, 2, 3);
});

test('destroy method calls query builder correctly with collection', function () {
    InstrumentModelDestroyStub::destroy(new BaseCollection([1, 2, 3]));
});

test('destroy method calls query builder correctly with instrument collection', function () {
    InstrumentModelDestroyStub::destroy(new Collection([
        new InstrumentModelDestroyStub(['id' => 1]),
        new InstrumentModelDestroyStub(['id' => 2]),
        new InstrumentModelDestroyStub(['id' => 3]),
    ]));
});

test('destroy method calls query builder correctly with multiple args', function () {
    InstrumentModelDestroyStub::destroy(1, 2, 3);
});

test('destroy method calls query builder correctly with empty ids', function () {
    $count = InstrumentModelEmptyDestroyStub::destroy([]);
    $this->assertSame(0, $count);
});

test('with method calls query builder correctly', function () {
    $result = InstrumentModelWithStub::with('foo', 'bar');
    $this->assertSame('foo', $result);
});

test('without method removes eager loaded relationship correctly', function () {
    $model = new InstrumentModelWithoutRelationStub;
    modelAddMockConnection($model);
    $instance = $model->newInstance()->newQuery()->without('foo');
    $this->assertEmpty($instance->getEagerLoads());
});

test('with only method loads relationship correctly', function () {
    $model = new InstrumentModelWithoutRelationStub();
    modelAddMockConnection($model);
    $instance = $model->newInstance()->newQuery()->withOnly('taylor');
    $this->assertNotNull($instance->getEagerLoads()['taylor']);
    $this->assertArrayNotHasKey('foo', $instance->getEagerLoads());
});

test('eager loading with columns', function () {
    $model = new InstrumentModelWithoutRelationStub;
    $instance = $model->newInstance()->newQuery()->with('foo:bar,baz', 'hadi');
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('select')->once()->with(['bar', 'baz']);
    $this->assertNotNull($instance->getEagerLoads()['hadi']);
    $this->assertNotNull($instance->getEagerLoads()['foo']);
    $closure = $instance->getEagerLoads()['foo'];
    $closure($builder);
});

test('with where has with specific columns', function () {
    $model = new InstrumentModelWithWhereHasStub;
    $instance = $model->newInstance()->newQuery()->withWhereHas('foo:diaa,fares');
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('select')->once()->with(['diaa', 'fares']);
    $this->assertNotNull($instance->getEagerLoads()['foo']);
    $closure = $instance->getEagerLoads()['foo'];
    $closure($builder);
});

test('with where has works in nested query', function () {
    $model = new InstrumentModelWithWhereHasStub;
    $instance = $model->newInstance()->newQuery()->where(fn (Builder $q) => $q->withWhereHas('foo:diaa,fares'));
    $builder = m::mock(Builder::class);
    $builder->shouldReceive('select')->once()->with(['diaa', 'fares']);
    $this->assertNotNull($instance->getEagerLoads()['foo']);
    $closure = $instance->getEagerLoads()['foo'];
    $closure($builder);
});

test('with method calls query builder correctly with array', function () {
    $result = InstrumentModelWithStub::with(['foo', 'bar']);
    $this->assertSame('foo', $result);
});

test('update process', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('where')->once()->with('id', '=', 1);
    $query->shouldReceive('update')->once()->with(['name' => 'taylor'])->andReturn(1);
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('updateTimestamps');
    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->once()->with('instrument.saving: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('until')->once()->with('instrument.updating: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('instrument.updated: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('instrument.saved: '.get_class($model), $model)->andReturn(true);

    $model->id = 1;
    $model->foo = 'bar';
    // make sure foo isn't synced so we can test that dirty attributes only are updated
    $model->syncOriginal();
    $model->name = 'taylor';
    $model->exists = true;
    $this->assertTrue($model->save());
});

test('update process doesnt override timestamps', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('where')->once()->with('id', '=', 1);
    $query->shouldReceive('update')->once()->with(['created_at' => 'foo', 'updated_at' => 'bar'])->andReturn(1);
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until');
    $events->shouldReceive('dispatch');

    $model->id = 1;
    $model->syncOriginal();
    $model->created_at = 'foo';
    $model->updated_at = 'bar';
    $model->exists = true;
    $this->assertTrue($model->save());
});

test('save is canceled if saving event returns false', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery'])->getMock();
    $query = m::mock(Builder::class);
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->once()->with('instrument.saving: '.get_class($model), $model)->andReturn(false);
    $model->exists = true;

    $this->assertFalse($model->save());
});

test('update is canceled if updating event returns false', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery'])->getMock();
    $query = m::mock(Builder::class);
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->once()->with('instrument.saving: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('until')->once()->with('instrument.updating: '.get_class($model), $model)->andReturn(false);
    $model->exists = true;
    $model->foo = 'bar';

    $this->assertFalse($model->save());
});

test('events can be fired with custom event objects', function () {
    $model = $this->getMockBuilder(InstrumentModelEventObjectStub::class)->onlyMethods(['newModelQuery'])->getMock();
    $query = m::mock(Builder::class);
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->once()->with(m::type(InstrumentModelSavingEventStub::class))->andReturn(false);
    $model->exists = true;

    $this->assertFalse($model->save());
});

test('update process without timestamps', function () {
    $model = $this->getMockBuilder(InstrumentModelEventObjectStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'fireModelEvent'])->getMock();
    $model->timestamps = false;
    $query = m::mock(Builder::class);
    $query->shouldReceive('where')->once()->with('id', '=', 1);
    $query->shouldReceive('update')->once()->with(['name' => 'taylor'])->andReturn(1);
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->never())->method('updateTimestamps');
    $model->expects($this->any())->method('fireModelEvent')->willReturn(true);

    $model->id = 1;
    $model->syncOriginal();
    $model->name = 'taylor';
    $model->exists = true;
    $this->assertTrue($model->save());
});

test('update uses old primary key', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('where')->once()->with('id', '=', 1);
    $query->shouldReceive('update')->once()->with(['id' => 2, 'foo' => 'bar'])->andReturn(1);
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('updateTimestamps');
    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->once()->with('instrument.saving: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('until')->once()->with('instrument.updating: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('instrument.updated: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('instrument.saved: '.get_class($model), $model)->andReturn(true);

    $model->id = 1;
    $model->syncOriginal();
    $model->id = 2;
    $model->foo = 'bar';
    $model->exists = true;

    $this->assertTrue($model->save());
});

test('timestamps are returned as objects', function () {
    $model = $this->getMockBuilder(InstrumentDateModelStub::class)->onlyMethods(['getDateFormat'])->getMock();
    $model->expects($this->any())->method('getDateFormat')->willReturn('Y-m-d');
    $model->setRawAttributes([
        'created_at' => '2012-12-04',
        'updated_at' => '2012-12-05',
    ]);

    $this->assertInstanceOf(Carbon::class, $model->created_at);
    $this->assertInstanceOf(Carbon::class, $model->updated_at);
});

test('timestamps are returned as objects from plain dates and timestamps', function () {
    $model = $this->getMockBuilder(InstrumentDateModelStub::class)->onlyMethods(['getDateFormat'])->getMock();
    $model->expects($this->any())->method('getDateFormat')->willReturn('Y-m-d H:i:s');
    $model->setRawAttributes([
        'created_at' => '2012-12-04',
        'updated_at' => $this->currentTime(),
    ]);

    $this->assertInstanceOf(Carbon::class, $model->created_at);
    $this->assertInstanceOf(Carbon::class, $model->updated_at);
});

test('timestamps are returned as objects on create', function () {
    $timestamps = [
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ];
    $model = new InstrumentDateModelStub;
    Model::setConnectionResolver($resolver = m::mock(ConnectionResolverInterface::class));
    $resolver->shouldReceive('connection')->andReturn($mockConnection = m::mock(stdClass::class));
    $mockConnection->shouldReceive('getQueryGrammar')->andReturn($mockConnection);
    $mockConnection->shouldReceive('getDateFormat')->andReturn('Y-m-d H:i:s');
    $instance = $model->newInstance($timestamps);
    $this->assertInstanceOf(Carbon::class, $instance->updated_at);
    $this->assertInstanceOf(Carbon::class, $instance->created_at);
});

test('date time attributes return null if set to null', function () {
    $timestamps = [
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ];
    $model = new InstrumentDateModelStub;
    Model::setConnectionResolver($resolver = m::mock(ConnectionResolverInterface::class));
    $resolver->shouldReceive('connection')->andReturn($mockConnection = m::mock(stdClass::class));
    $mockConnection->shouldReceive('getQueryGrammar')->andReturn($mockConnection);
    $mockConnection->shouldReceive('getDateFormat')->andReturn('Y-m-d H:i:s');
    $instance = $model->newInstance($timestamps);

    $instance->created_at = null;
    $this->assertNull($instance->created_at);
});

test('timestamps are created from strings and integers', function () {
    $model = new InstrumentDateModelStub;
    $model->created_at = '2013-05-22 00:00:00';
    $this->assertInstanceOf(Carbon::class, $model->created_at);

    $model = new InstrumentDateModelStub;
    $model->created_at = $this->currentTime();
    $this->assertInstanceOf(Carbon::class, $model->created_at);

    $model = new InstrumentDateModelStub;
    $model->created_at = 0;
    $this->assertInstanceOf(Carbon::class, $model->created_at);

    $model = new InstrumentDateModelStub;
    $model->created_at = '2012-01-01';
    $this->assertInstanceOf(Carbon::class, $model->created_at);
});

test('from date time', function () {
    $model = new InstrumentModelStub;

    $value = Carbon::parse('2015-04-17 22:59:01');
    $this->assertSame('2015-04-17 22:59:01', $model->fromDateTime($value));

    $value = new DateTime('2015-04-17 22:59:01');
    $this->assertInstanceOf(DateTime::class, $value);
    $this->assertInstanceOf(DateTimeInterface::class, $value);
    $this->assertSame('2015-04-17 22:59:01', $model->fromDateTime($value));

    $value = new DateTimeImmutable('2015-04-17 22:59:01');
    $this->assertInstanceOf(DateTimeImmutable::class, $value);
    $this->assertInstanceOf(DateTimeInterface::class, $value);
    $this->assertSame('2015-04-17 22:59:01', $model->fromDateTime($value));

    $value = '2015-04-17 22:59:01';
    $this->assertSame('2015-04-17 22:59:01', $model->fromDateTime($value));

    $value = '2015-04-17';
    $this->assertSame('2015-04-17 00:00:00', $model->fromDateTime($value));

    $value = '2015-4-17';
    $this->assertSame('2015-04-17 00:00:00', $model->fromDateTime($value));

    $value = '1429311541';
    $this->assertSame('2015-04-17 22:59:01', $model->fromDateTime($value));

    $this->assertNull($model->fromDateTime(null));
});

test('from date time milliseconds', function () {
    $model = $this->getMockBuilder('Tests\Database\InstrumentDateModelStub')->onlyMethods(['getDateFormat'])->getMock();
    $model->expects($this->any())->method('getDateFormat')->willReturn('Y-m-d H:s.vi');
    $model->setRawAttributes([
        'created_at' => '2012-12-04 22:59.32130',
    ]);

    $this->assertInstanceOf(Carbon::class, $model->created_at);
    $this->assertSame('22:30:59.321000', $model->created_at->format('H:i:s.u'));
});

test('insert process', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('updateTimestamps');

    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->once()->with('instrument.saving: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('until')->once()->with('instrument.creating: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('instrument.created: '.get_class($model), $model);
    $events->shouldReceive('dispatch')->once()->with('instrument.saved: '.get_class($model), $model);

    $model->name = 'taylor';
    $model->exists = false;
    $this->assertTrue($model->save());
    $this->assertEquals(1, $model->id);
    $this->assertTrue($model->exists);

    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insert')->once()->with(['name' => 'taylor']);
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('updateTimestamps');
    $model->setIncrementing(false);

    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->once()->with('instrument.saving: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('until')->once()->with('instrument.creating: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->once()->with('instrument.created: '.get_class($model), $model);
    $events->shouldReceive('dispatch')->once()->with('instrument.saved: '.get_class($model), $model);

    $model->name = 'taylor';
    $model->exists = false;
    $this->assertTrue($model->save());
    $this->assertNull($model->id);
    $this->assertTrue($model->exists);
});

test('insert is canceled if creating event returns false', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->once()->with('instrument.saving: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('until')->once()->with('instrument.creating: '.get_class($model), $model)->andReturn(false);

    $this->assertFalse($model->save());
    $this->assertFalse($model->exists);
});

test('delete properly deletes model', function () {
    $model = $this->getMockBuilder(Model::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'touchOwners'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('where')->once()->with('id', '=', 1)->andReturn($query);
    $query->shouldReceive('delete')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('touchOwners');
    $model->exists = true;
    $model->id = 1;
    $model->delete();
});

test('push no relations', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('updateTimestamps');

    $model->name = 'taylor';
    $model->exists = false;

    $this->assertTrue($model->push());
    $this->assertEquals(1, $model->id);
    $this->assertTrue($model->exists);
});

test('push empty one relation', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('updateTimestamps');

    $model->name = 'taylor';
    $model->exists = false;
    $model->setRelation('relationOne', null);

    $this->assertTrue($model->push());
    $this->assertEquals(1, $model->id);
    $this->assertTrue($model->exists);
    $this->assertNull($model->relationOne);
});

test('push one relation', function () {
    $related1 = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with(['name' => 'related1'], 'id')->andReturn(2);
    $query->shouldReceive('getConnection')->once();
    $related1->expects($this->once())->method('newModelQuery')->willReturn($query);
    $related1->expects($this->once())->method('updateTimestamps');
    $related1->name = 'related1';
    $related1->exists = false;

    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('updateTimestamps');

    $model->name = 'taylor';
    $model->exists = false;
    $model->setRelation('relationOne', $related1);

    $this->assertTrue($model->push());
    $this->assertEquals(1, $model->id);
    $this->assertTrue($model->exists);
    $this->assertEquals(2, $model->relationOne->id);
    $this->assertTrue($model->relationOne->exists);
    $this->assertEquals(2, $related1->id);
    $this->assertTrue($related1->exists);
});

test('push empty many relation', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('updateTimestamps');

    $model->name = 'taylor';
    $model->exists = false;
    $model->setRelation('relationMany', new Collection([]));

    $this->assertTrue($model->push());
    $this->assertEquals(1, $model->id);
    $this->assertTrue($model->exists);
    $this->assertCount(0, $model->relationMany);
});

test('push many relation', function () {
    $related1 = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with(['name' => 'related1'], 'id')->andReturn(2);
    $query->shouldReceive('getConnection')->once();
    $related1->expects($this->once())->method('newModelQuery')->willReturn($query);
    $related1->expects($this->once())->method('updateTimestamps');
    $related1->name = 'related1';
    $related1->exists = false;

    $related2 = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with(['name' => 'related2'], 'id')->andReturn(3);
    $query->shouldReceive('getConnection')->once();
    $related2->expects($this->once())->method('newModelQuery')->willReturn($query);
    $related2->expects($this->once())->method('updateTimestamps');
    $related2->name = 'related2';
    $related2->exists = false;

    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with(['name' => 'taylor'], 'id')->andReturn(1);
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);
    $model->expects($this->once())->method('updateTimestamps');

    $model->name = 'taylor';
    $model->exists = false;
    $model->setRelation('relationMany', new Collection([$related1, $related2]));

    $this->assertTrue($model->push());
    $this->assertEquals(1, $model->id);
    $this->assertTrue($model->exists);
    $this->assertCount(2, $model->relationMany);
    $this->assertEquals([2, 3], $model->relationMany->pluck('id')->all());
});

test('push circular relations', function () {
    $parent = new InstrumentModelWithRecursiveRelationshipsStub(['id' => 1, 'parent_id' => null]);
    $lastId = $parent->id;
    $parent->setRelation('self', $parent);

    $children = new Collection();
    for ($count = 0; $count < 2; $count++) {
        $child = new InstrumentModelWithRecursiveRelationshipsStub(['id' => ++$lastId, 'parent_id' => $parent->id]);
        $child->setRelation('parent', $parent);
        $child->setRelation('self', $child);
        $children->push($child);
    }
    $parent->setRelation('children', $children);

    try {
        $this->assertTrue($parent->push());
    } catch (\RuntimeException $e) {
        $this->fail($e->getMessage());
    }
});

test('new query returns instrument query builder', function () {
    $conn = m::mock(Connection::class);
    $grammar = m::mock(Grammar::class);
    $processor = m::mock(Processor::class);
    InstrumentModelStub::setConnectionResolver($resolver = m::mock(ConnectionResolverInterface::class));
    $conn->shouldReceive('query')->andReturnUsing(function () use ($conn, $grammar, $processor) {
        return new BaseBuilder($conn, $grammar, $processor);
    });
    $resolver->shouldReceive('connection')->andReturn($conn);
    $model = new InstrumentModelStub;
    $builder = $model->newQuery();
    $this->assertInstanceOf(Builder::class, $builder);
});

test('get and set table operations', function () {
    $model = new InstrumentModelStub;
    $this->assertSame('stub', $model->getTable());
    $model->setTable('foo');
    $this->assertSame('foo', $model->getTable());
});

test('get key returns value of primary key', function () {
    $model = new InstrumentModelStub;
    $model->id = 1;
    $this->assertEquals(1, $model->getKey());
    $this->assertSame('id', $model->getKeyName());
});

test('connection management', function () {
    InstrumentModelStub::setConnectionResolver($resolver = m::mock(ConnectionResolverInterface::class));
    $model = m::mock(InstrumentModelStub::class.'[getConnectionName,connection]');

    $retval = $model->setConnection('foo');
    $this->assertEquals($retval, $model);
    $this->assertSame('foo', $model->connection);

    $model->shouldReceive('getConnectionName')->once()->andReturn('somethingElse');
    $resolver->shouldReceive('connection')->once()->with('somethingElse')->andReturn('bar');

    $this->assertSame('bar', $model->getConnection());
});

test('connection enums with a string', function () {
    InstrumentModelStub::setConnectionResolver($resolver = m::mock(ConnectionResolverInterface::class));
    $model = new InstrumentModelStub;

    $retval = $model->setConnection('Foo');
    $this->assertEquals($retval, $model);
    $this->assertSame('Foo', $model->getConnectionName());

    $resolver->shouldReceive('connection')->once()->with('Foo')->andReturn('bar');

    $this->assertSame('bar', $model->getConnection());
});

test('connection enums with a unit enum', function () {
    InstrumentModelStub::setConnectionResolver($resolver = m::mock(ConnectionResolverInterface::class));
    $model = new InstrumentModelStub;

    $retval = $model->setConnection(ConnectionName::Foo);
    $this->assertEquals($retval, $model);
    $this->assertSame('Foo', $model->getConnectionName());

    $resolver->shouldReceive('connection')->once()->with('Foo')->andReturn('bar');

    $this->assertSame('bar', $model->getConnection());
});

test('connection enums with a backed enum', function () {
    InstrumentModelStub::setConnectionResolver($resolver = m::mock(ConnectionResolverInterface::class));
    $model = new InstrumentModelStub;

    $retval = $model->setConnection(ConnectionNameBacked::Foo);
    $this->assertEquals($retval, $model);
    $this->assertSame('Foo', $model->getConnectionName());

    $resolver->shouldReceive('connection')->once()->with('Foo')->andReturn('bar');

    $this->assertSame('bar', $model->getConnection());
});

test('to array', function () {
    $model = new InstrumentModelStub;
    $model->name = 'foo';
    $model->age = null;
    $model->password = 'password1';
    $model->setHidden(['password']);
    $model->setRelation('names', new BaseCollection([
        new InstrumentModelStub(['bar' => 'baz']), new InstrumentModelStub(['bam' => 'boom']),
    ]));
    $model->setRelation('partner', new InstrumentModelStub(['name' => 'abby']));
    $model->setRelation('group', null);
    $model->setRelation('multi', new BaseCollection);
    $array = $model->toArray();

    $this->assertIsArray($array);
    $this->assertSame('foo', $array['name']);
    $this->assertSame('baz', $array['names'][0]['bar']);
    $this->assertSame('boom', $array['names'][1]['bam']);
    $this->assertSame('abby', $array['partner']['name']);
    $this->assertNull($array['group']);
    $this->assertEquals([], $array['multi']);
    $this->assertFalse(isset($array['password']));

    $model->setAppends(['appendable']);
    $array = $model->toArray();
    $this->assertSame('appended', $array['appendable']);
});

test('to array with circular relations', function () {
    $parent = new InstrumentModelWithRecursiveRelationshipsStub(['id' => 1, 'parent_id' => null]);
    $lastId = $parent->id;
    $parent->setRelation('self', $parent);

    $children = new Collection();
    for ($count = 0; $count < 2; $count++) {
        $child = new InstrumentModelWithRecursiveRelationshipsStub(['id' => ++$lastId, 'parent_id' => $parent->id]);
        $child->setRelation('parent', $parent);
        $child->setRelation('self', $child);
        $children->push($child);
    }
    $parent->setRelation('children', $children);

    try {
        $this->assertSame(
            [
                'id' => 1,
                'parent_id' => null,
                'self' => ['id' => 1, 'parent_id' => null],
                'children' => [
                    [
                        'id' => 2,
                        'parent_id' => 1,
                        'parent' => ['id' => 1, 'parent_id' => null],
                        'self' => ['id' => 2, 'parent_id' => 1],
                    ],
                    [
                        'id' => 3,
                        'parent_id' => 1,
                        'parent' => ['id' => 1, 'parent_id' => null],
                        'self' => ['id' => 3, 'parent_id' => 1],
                    ],
                ],
            ],
            $parent->toArray()
        );
    } catch (\RuntimeException $e) {
        $this->fail($e->getMessage());
    }
});

test('get queueable relations with circular relations', function () {
    $parent = new InstrumentModelWithRecursiveRelationshipsStub(['id' => 1, 'parent_id' => null]);
    $lastId = $parent->id;
    $parent->setRelation('self', $parent);

    $children = new Collection();
    for ($count = 0; $count < 2; $count++) {
        $child = new InstrumentModelWithRecursiveRelationshipsStub(['id' => ++$lastId, 'parent_id' => $parent->id]);
        $child->setRelation('parent', $parent);
        $child->setRelation('self', $child);
        $children->push($child);
    }
    $parent->setRelation('children', $children);

    try {
        $this->assertSame(
            [
                'self',
                'children',
                'children.parent',
                'children.self',
            ],
            $parent->getQueueableRelations()
        );
    } catch (\RuntimeException $e) {
        $this->fail($e->getMessage());
    }
});

test('visible creates array whitelist', function () {
    $model = new InstrumentModelStub;
    $model->setVisible(['name']);
    $model->name = 'Taylor';
    $model->age = 26;
    $array = $model->toArray();

    $this->assertEquals(['name' => 'Taylor'], $array);
});

test('hidden can also exclude relationships', function () {
    $model = new InstrumentModelStub;
    $model->name = 'Taylor';
    $model->setRelation('foo', ['bar']);
    $model->setHidden(['foo', 'list_items', 'password']);
    $array = $model->toArray();

    $this->assertEquals(['name' => 'Taylor'], $array);
});

test('get arrayable relations function exclude hidden relationships', function () {
    $model = new InstrumentModelStub;

    $class = new ReflectionClass($model);
    $method = $class->getMethod('getArrayableRelations');

    $model->setRelation('foo', ['bar']);
    $model->setRelation('bam', ['boom']);
    $model->setHidden(['foo']);

    $array = $method->invokeArgs($model, []);

    $this->assertSame(['bam' => ['boom']], $array);
});

test('to array snake attributes', function () {
    $model = new InstrumentModelStub;
    $model->setRelation('namesList', new BaseCollection([
        new InstrumentModelStub(['bar' => 'baz']), new InstrumentModelStub(['bam' => 'boom']),
    ]));
    $array = $model->toArray();

    $this->assertSame('baz', $array['names_list'][0]['bar']);
    $this->assertSame('boom', $array['names_list'][1]['bam']);

    $model = new InstrumentModelCamelStub;
    $model->setRelation('namesList', new BaseCollection([
        new InstrumentModelStub(['bar' => 'baz']), new InstrumentModelStub(['bam' => 'boom']),
    ]));
    $array = $model->toArray();

    $this->assertSame('baz', $array['namesList'][0]['bar']);
    $this->assertSame('boom', $array['namesList'][1]['bam']);
});

test('to array uses mutators', function () {
    $model = new InstrumentModelStub;
    $model->list_items = [1, 2, 3];
    $array = $model->toArray();

    $this->assertEquals([1, 2, 3], $array['list_items']);
});

test('hidden', function () {
    $model = new InstrumentModelStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
    $model->setHidden(['age', 'id']);
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayNotHasKey('age', $array);
});

test('merge hidden merges hidden', function () {
    $model = new InstrumentModelHiddenStub;

    $hiddenCount = count($model->getHidden());
    $this->assertContains('foo', $model->getHidden());

    $model->mergeHidden(['bar']);
    $this->assertCount($hiddenCount + 1, $model->getHidden());
    $this->assertContains('bar', $model->getHidden());
});

test('visible', function () {
    $model = new InstrumentModelStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
    $model->setVisible(['name', 'id']);
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayNotHasKey('age', $array);
});

test('merge visible merges visible', function () {
    $model = new InstrumentModelVisibleStub;

    $visibleCount = count($model->getVisible());
    $this->assertContains('foo', $model->getVisible());

    $model->mergeVisible(['bar']);
    $this->assertCount($visibleCount + 1, $model->getVisible());
    $this->assertContains('bar', $model->getVisible());
});

test('dynamic hidden', function () {
    $model = new InstrumentModelDynamicHiddenStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayNotHasKey('age', $array);
});

test('with hidden', function () {
    $model = new InstrumentModelStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
    $model->setHidden(['age', 'id']);
    $model->makeVisible('age');
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayHasKey('age', $array);
    $this->assertArrayNotHasKey('id', $array);
});

test('make hidden', function () {
    $model = new InstrumentModelStub(['name' => 'foo', 'age' => 'bar', 'address' => 'foobar', 'id' => 'baz']);
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayHasKey('age', $array);
    $this->assertArrayHasKey('address', $array);
    $this->assertArrayHasKey('id', $array);

    $array = $model->makeHidden('address')->toArray();
    $this->assertArrayNotHasKey('address', $array);
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayHasKey('age', $array);
    $this->assertArrayHasKey('id', $array);

    $array = $model->makeHidden(['name', 'age'])->toArray();
    $this->assertArrayNotHasKey('name', $array);
    $this->assertArrayNotHasKey('age', $array);
    $this->assertArrayNotHasKey('address', $array);
    $this->assertArrayHasKey('id', $array);
});

test('dynamic visible', function () {
    $model = new InstrumentModelDynamicVisibleStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayNotHasKey('age', $array);
});

test('make visible if', function () {
    $model = new InstrumentModelStub(['name' => 'foo', 'age' => 'bar', 'id' => 'baz']);
    $model->setHidden(['age', 'id']);
    $model->makeVisibleIf(true, 'age');
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayHasKey('age', $array);
    $this->assertArrayNotHasKey('id', $array);

    $model->setHidden(['age', 'id']);
    $model->makeVisibleIf(false, 'age');
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayNotHasKey('age', $array);
    $this->assertArrayNotHasKey('id', $array);

    $model->setHidden(['age', 'id']);
    $model->makeVisibleIf(function ($model) {
        return ! is_null($model->name);
    }, 'age');
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayHasKey('age', $array);
    $this->assertArrayNotHasKey('id', $array);
});

test('make hidden if', function () {
    $model = new InstrumentModelStub(['name' => 'foo', 'age' => 'bar', 'address' => 'foobar', 'id' => 'baz']);
    $array = $model->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayHasKey('age', $array);
    $this->assertArrayHasKey('address', $array);
    $this->assertArrayHasKey('id', $array);

    $array = $model->makeHiddenIf(true, 'address')->toArray();
    $this->assertArrayNotHasKey('address', $array);
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayHasKey('age', $array);
    $this->assertArrayHasKey('id', $array);

    $model->makeVisible('address');

    $array = $model->makeHiddenIf(false, ['name', 'age'])->toArray();
    $this->assertArrayHasKey('name', $array);
    $this->assertArrayHasKey('age', $array);
    $this->assertArrayHasKey('address', $array);
    $this->assertArrayHasKey('id', $array);

    $array = $model->makeHiddenIf(function ($model) {
        return ! is_null($model->id);
    }, ['name', 'age'])->toArray();
    $this->assertArrayHasKey('address', $array);
    $this->assertArrayNotHasKey('name', $array);
    $this->assertArrayNotHasKey('age', $array);
    $this->assertArrayHasKey('id', $array);
});

test('fillable', function () {
    $model = new InstrumentModelStub;
    $model->fillable(['name', 'age']);
    $model->fill(['name' => 'foo', 'age' => 'bar']);
    $this->assertSame('foo', $model->name);
    $this->assertSame('bar', $model->age);
});

test('qualify column', function () {
    $model = new InstrumentModelStub;

    $this->assertSame('stub.column', $model->qualifyColumn('column'));
});

test('force fill method fills guarded attributes', function () {
    $model = (new InstrumentModelSaveStub)->forceFill(['id' => 21]);
    $this->assertEquals(21, $model->id);
});

test('filling j s o n attributes', function () {
    $model = new InstrumentModelStub;
    $model->fillable(['meta->name', 'meta->price', 'meta->size->width']);
    $model->fill(['meta->name' => 'foo', 'meta->price' => 'bar', 'meta->size->width' => 'baz']);
    $this->assertEquals(
        ['meta' => json_encode(['name' => 'foo', 'price' => 'bar', 'size' => ['width' => 'baz']])],
        $model->toArray()
    );

    $model = new InstrumentModelStub(['meta' => json_encode(['name' => 'Taylor'])]);
    $model->fillable(['meta->name', 'meta->price', 'meta->size->width']);
    $model->fill(['meta->name' => 'foo', 'meta->price' => 'bar', 'meta->size->width' => 'baz']);
    $this->assertEquals(
        ['meta' => json_encode(['name' => 'foo', 'price' => 'bar', 'size' => ['width' => 'baz']])],
        $model->toArray()
    );
});

test('unguard allows anything to be set', function () {
    $model = new InstrumentModelStub;
    InstrumentModelStub::unguard();
    $model->guard(['*']);
    $model->fill(['name' => 'foo', 'age' => 'bar']);
    $this->assertSame('foo', $model->name);
    $this->assertSame('bar', $model->age);
    InstrumentModelStub::unguard(false);
});

test('underscore properties are not filled', function () {
    $model = new InstrumentModelStub;
    $model->fill(['_method' => 'PUT']);
    $this->assertEquals([], $model->getAttributes());
});

test('guarded', function () {
    $model = new InstrumentModelStub;

    InstrumentModelStub::setConnectionResolver($resolver = m::mock(Resolver::class));
    $resolver->shouldReceive('connection')->andReturn($connection = m::mock(stdClass::class));
    $connection->shouldReceive('getSchemaBuilder->getColumnListing')->andReturn(['name', 'age', 'foo']);

    $model->guard(['name', 'age']);
    $model->fill(['name' => 'foo', 'age' => 'bar', 'foo' => 'bar']);
    $this->assertFalse(isset($model->name));
    $this->assertFalse(isset($model->age));
    $this->assertSame('bar', $model->foo);

    $model = new InstrumentModelStub;
    $model->guard(['name', 'age']);
    $model->fill(['Foo' => 'bar']);
    $this->assertFalse(isset($model->Foo));

    $handledMassAssignmentExceptions = 0;

    Model::preventSilentlyDiscardingAttributes();

    $model = new InstrumentModelStub;
    $model->guard(['name', 'age']);
    $model->fill(['Foo' => 'bar']);

    Model::preventSilentlyDiscardingAttributes(false);
})->throws(MassAssignmentException::class);

test('guarded with fillable config', function () {
    $model = new InstrumentModelStub;
    $model::unguard();

    InstrumentModelStub::setConnectionResolver($resolver = m::mock(Resolver::class));
    $resolver->shouldReceive('connection')->andReturn($connection = m::mock(stdClass::class));
    $connection->shouldReceive('getSchemaBuilder->getColumnListing')->andReturn(['name', 'age', 'foo']);

    $model->guard([]);
    $model->fillable(['name']);
    $model->fill(['name' => 'Leto Atreides', 'age' => 51]);

    self::assertSame(
        ['name' => 'Leto Atreides', 'age' => 51],
        $model->getAttributes(),
    );

    $model::reguard();
});

test('uses overridden handler when discarding attributes', function () {
    InstrumentModelStub::setConnectionResolver($resolver = m::mock(Resolver::class));
    $resolver->shouldReceive('connection')->andReturn($connection = m::mock(stdClass::class));
    $connection->shouldReceive('getSchemaBuilder->getColumnListing')->andReturn(['name', 'age', 'foo']);

    Model::preventSilentlyDiscardingAttributes();

    $callbackModel = null;
    $callbackKeys = null;
    Model::handleDiscardedAttributeViolationUsing(function ($model, $keys) use (&$callbackModel, &$callbackKeys) {
        $callbackModel = $model;
        $callbackKeys = $keys;
    });

    $model = new InstrumentModelStub;
    $model->guard(['name', 'age']);
    $model->fill(['Foo' => 'bar']);

    $this->assertInstanceOf(InstrumentModelStub::class, $callbackModel);
    $this->assertEquals(['Foo'], $callbackKeys);

    Model::preventSilentlyDiscardingAttributes(false);
    Model::handleDiscardedAttributeViolationUsing(null);
});

test('fillable overrides guarded', function () {
    Model::preventSilentlyDiscardingAttributes(false);

    $model = new InstrumentModelStub;
    $model->guard([]);
    $model->fillable(['age', 'foo']);
    $model->fill(['name' => 'foo', 'age' => 'bar', 'foo' => 'bar']);
    $this->assertFalse(isset($model->name));
    $this->assertSame('bar', $model->age);
    $this->assertSame('bar', $model->foo);
});

test('global guarded', function () {
    $model = new InstrumentModelStub;
    $model->guard(['*']);
    $model->fill(['name' => 'foo', 'age' => 'bar', 'votes' => 'baz']);
})->throws(MassAssignmentException::class, 'name');

test('unguarded runs callback while being unguarded', function () {
    $model = Model::unguarded(function () {
        return (new InstrumentModelStub)->guard(['*'])->fill(['name' => 'Taylor']);
    });
    $this->assertSame('Taylor', $model->name);
    $this->assertFalse(Model::isUnguarded());
});

test('unguarded call does not change unguarded state', function () {
    Model::unguard();
    $model = Model::unguarded(function () {
        return (new InstrumentModelStub)->guard(['*'])->fill(['name' => 'Taylor']);
    });
    $this->assertSame('Taylor', $model->name);
    $this->assertTrue(Model::isUnguarded());
    Model::reguard();
});

test('unguarded call does not change unguarded state on exception', function () {
    try {
        Model::unguarded(function () {
            throw new Exception;
        });
    } catch (Exception) {
        // ignore the exception
    }
    $this->assertFalse(Model::isUnguarded());
});

test('has one creates proper relation', function () {
    $model = new InstrumentModelStub;
    modelAddMockConnection($model);
    $relation = $model->hasOne(InstrumentModelSaveStub::class);
    $this->assertSame('save_stub.instrument_model_stub_id', $relation->getQualifiedForeignKeyName());

    $model = new InstrumentModelStub;
    modelAddMockConnection($model);
    $relation = $model->hasOne(InstrumentModelSaveStub::class, 'foo');
    $this->assertSame('save_stub.foo', $relation->getQualifiedForeignKeyName());
    $this->assertSame($model, $relation->getParent());
    $this->assertInstanceOf(InstrumentModelSaveStub::class, $relation->getQuery()->getModel());
});

test('morph one creates proper relation', function () {
    $model = new InstrumentModelStub;
    modelAddMockConnection($model);
    $relation = $model->morphOne(InstrumentModelSaveStub::class, 'morph');
    $this->assertSame('save_stub.morph_id', $relation->getQualifiedForeignKeyName());
    $this->assertSame('save_stub.morph_type', $relation->getQualifiedMorphType());
    $this->assertEquals(InstrumentModelStub::class, $relation->getMorphClass());
});

test('correct morph class is returned', function () {
    Relation::morphMap(['alias' => 'AnotherModel']);
    $model = new InstrumentModelStub;

    try {
        $this->assertEquals(InstrumentModelStub::class, $model->getMorphClass());
    } finally {
        Relation::morphMap([], false);
    }
});

test('has many creates proper relation', function () {
    $model = new InstrumentModelStub;
    modelAddMockConnection($model);
    $relation = $model->hasMany(InstrumentModelSaveStub::class);
    $this->assertSame('save_stub.instrument_model_stub_id', $relation->getQualifiedForeignKeyName());

    $model = new InstrumentModelStub;
    modelAddMockConnection($model);
    $relation = $model->hasMany(InstrumentModelSaveStub::class, 'foo');

    $this->assertSame('save_stub.foo', $relation->getQualifiedForeignKeyName());
    $this->assertSame($model, $relation->getParent());
    $this->assertInstanceOf(InstrumentModelSaveStub::class, $relation->getQuery()->getModel());
});

test('morph many creates proper relation', function () {
    $model = new InstrumentModelStub;
    modelAddMockConnection($model);
    $relation = $model->morphMany(InstrumentModelSaveStub::class, 'morph');
    $this->assertSame('save_stub.morph_id', $relation->getQualifiedForeignKeyName());
    $this->assertSame('save_stub.morph_type', $relation->getQualifiedMorphType());
    $this->assertEquals(InstrumentModelStub::class, $relation->getMorphClass());
});

test('belongs to creates proper relation', function () {
    $model = new InstrumentModelStub;
    modelAddMockConnection($model);
    $relation = $model->belongsToStub();
    $this->assertSame('belongs_to_stub_id', $relation->getForeignKeyName());
    $this->assertSame($model, $relation->getParent());
    $this->assertInstanceOf(InstrumentModelSaveStub::class, $relation->getQuery()->getModel());

    $model = new InstrumentModelStub;
    modelAddMockConnection($model);
    $relation = $model->belongsToExplicitKeyStub();
    $this->assertSame('foo', $relation->getForeignKeyName());
});

test('morph to creates proper relation', function () {
    $model = new InstrumentModelStub;
    modelAddMockConnection($model);

    // $this->morphTo();
    $model->setAttribute('morph_to_stub_type', InstrumentModelSaveStub::class);
    $relation = $model->morphToStub();
    $this->assertSame('morph_to_stub_id', $relation->getForeignKeyName());
    $this->assertSame('morph_to_stub_type', $relation->getMorphType());
    $this->assertSame('morphToStub', $relation->getRelationName());
    $this->assertSame($model, $relation->getParent());
    $this->assertInstanceOf(InstrumentModelSaveStub::class, $relation->getQuery()->getModel());

    // $this->morphTo(null, 'type', 'id');
    $relation2 = $model->morphToStubWithKeys();
    $this->assertSame('id', $relation2->getForeignKeyName());
    $this->assertSame('type', $relation2->getMorphType());
    $this->assertSame('morphToStubWithKeys', $relation2->getRelationName());

    // $this->morphTo('someName');
    $relation3 = $model->morphToStubWithName();
    $this->assertSame('some_name_id', $relation3->getForeignKeyName());
    $this->assertSame('some_name_type', $relation3->getMorphType());
    $this->assertSame('someName', $relation3->getRelationName());

    // $this->morphTo('someName', 'type', 'id');
    $relation4 = $model->morphToStubWithNameAndKeys();
    $this->assertSame('id', $relation4->getForeignKeyName());
    $this->assertSame('type', $relation4->getMorphType());
    $this->assertSame('someName', $relation4->getRelationName());
});

test('belongs to many creates proper relation', function () {
    $model = new InstrumentModelStub;
    modelAddMockConnection($model);

    $relation = $model->belongsToMany(InstrumentModelSaveStub::class);
    $this->assertSame('instrument_model_save_stub_instrument_model_stub.instrument_model_stub_id', $relation->getQualifiedForeignPivotKeyName());
    $this->assertSame('instrument_model_save_stub_instrument_model_stub.instrument_model_save_stub_id', $relation->getQualifiedRelatedPivotKeyName());
    $this->assertSame($model, $relation->getParent());
    $this->assertInstanceOf(InstrumentModelSaveStub::class, $relation->getQuery()->getModel());
    $this->assertEquals(__FUNCTION__, $relation->getRelationName());

    $model = new InstrumentModelStub;
    modelAddMockConnection($model);
    $relation = $model->belongsToMany(InstrumentModelSaveStub::class, 'table', 'foreign', 'other');
    $this->assertSame('table.foreign', $relation->getQualifiedForeignPivotKeyName());
    $this->assertSame('table.other', $relation->getQualifiedRelatedPivotKeyName());
    $this->assertSame($model, $relation->getParent());
    $this->assertInstanceOf(InstrumentModelSaveStub::class, $relation->getQuery()->getModel());
});

test('relations with varied connections', function () {
    // Has one
    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->hasOne(InstrumentNoConnectionModelStub::class);
    $this->assertSame('non_default', $relation->getRelated()->getConnectionName());

    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->hasOne(InstrumentDifferentConnectionModelStub::class);
    $this->assertSame('different_connection', $relation->getRelated()->getConnectionName());

    // Morph One
    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->morphOne(InstrumentNoConnectionModelStub::class, 'type');
    $this->assertSame('non_default', $relation->getRelated()->getConnectionName());

    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->morphOne(InstrumentDifferentConnectionModelStub::class, 'type');
    $this->assertSame('different_connection', $relation->getRelated()->getConnectionName());

    // Belongs to
    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->belongsTo(InstrumentNoConnectionModelStub::class);
    $this->assertSame('non_default', $relation->getRelated()->getConnectionName());

    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->belongsTo(InstrumentDifferentConnectionModelStub::class);
    $this->assertSame('different_connection', $relation->getRelated()->getConnectionName());

    // has many
    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->hasMany(InstrumentNoConnectionModelStub::class);
    $this->assertSame('non_default', $relation->getRelated()->getConnectionName());

    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->hasMany(InstrumentDifferentConnectionModelStub::class);
    $this->assertSame('different_connection', $relation->getRelated()->getConnectionName());

    // has many through
    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->hasManyThrough(InstrumentNoConnectionModelStub::class, InstrumentModelSaveStub::class);
    $this->assertSame('non_default', $relation->getRelated()->getConnectionName());

    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->hasManyThrough(InstrumentDifferentConnectionModelStub::class, InstrumentModelSaveStub::class);
    $this->assertSame('different_connection', $relation->getRelated()->getConnectionName());

    // belongs to many
    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->belongsToMany(InstrumentNoConnectionModelStub::class);
    $this->assertSame('non_default', $relation->getRelated()->getConnectionName());

    $model = new InstrumentModelStub;
    $model->setConnection('non_default');
    modelAddMockConnection($model);
    $relation = $model->belongsToMany(InstrumentDifferentConnectionModelStub::class);
    $this->assertSame('different_connection', $relation->getRelated()->getConnectionName());
});

test('models assume their name', function () {
    require_once __DIR__.'/stubs/InstrumentModelNamespacedStub.php';

    $model = new InstrumentModelWithoutTableStub;
    $this->assertSame('instrument_model_without_table_stubs', $model->getTable());

    $namespacedModel = new InstrumentModelNamespacedStub;
    $this->assertSame('instrument_model_namespaced_stubs', $namespacedModel->getTable());
});

test('the mutator cache is populated', function () {
    $class = new InstrumentModelStub;

    $expectedAttributes = [
        'list_items',
        'password',
        'appendable',
    ];

    $this->assertEquals($expectedAttributes, $class->getMutatedAttributes());
});

test('route key is primary key', function () {
    $model = new InstrumentModelNonIncrementingStub;
    $model->id = 'foo';
    $this->assertSame('foo', $model->getRouteKey());
});

test('route name is primary key name', function () {
    $model = new InstrumentModelStub;
    $this->assertSame('id', $model->getRouteKeyName());
});

test('clone model makes a fresh copy of the model', function () {
    $class = new InstrumentModelStub;
    $class->id = 1;
    $class->exists = true;
    $class->first = 'taylor';
    $class->last = 'otwell';
    $class->created_at = $class->freshTimestamp();
    $class->updated_at = $class->freshTimestamp();
    $class->setRelation('foo', ['bar']);

    $clone = $class->replicate();

    $this->assertNull($clone->id);
    $this->assertFalse($clone->exists);
    $this->assertSame('taylor', $clone->first);
    $this->assertSame('otwell', $clone->last);
    $this->assertArrayNotHasKey('created_at', $clone->getAttributes());
    $this->assertArrayNotHasKey('updated_at', $clone->getAttributes());
    $this->assertEquals(['bar'], $clone->foo);
});

test('clone model makes a fresh copy of the model when model has uuid primary key', function () {
    $class = new InstrumentPrimaryUuidModelStub();
    $class->uuid = 'ccf55569-bc4a-4450-875f-b5cffb1b34ec';
    $class->exists = true;
    $class->first = 'taylor';
    $class->last = 'otwell';
    $class->created_at = $class->freshTimestamp();
    $class->updated_at = $class->freshTimestamp();
    $class->setRelation('foo', ['bar']);

    $clone = $class->replicate();

    $this->assertNull($clone->uuid);
    $this->assertFalse($clone->exists);
    $this->assertSame('taylor', $clone->first);
    $this->assertSame('otwell', $clone->last);
    $this->assertArrayNotHasKey('created_at', $clone->getAttributes());
    $this->assertArrayNotHasKey('updated_at', $clone->getAttributes());
    $this->assertEquals(['bar'], $clone->foo);
});

test('clone model makes a fresh copy of the model when model has uuid', function () {
    $class = new InstrumentNonPrimaryUuidModelStub();
    $class->id = 1;
    $class->uuid = 'ccf55569-bc4a-4450-875f-b5cffb1b34ec';
    $class->exists = true;
    $class->first = 'taylor';
    $class->last = 'otwell';
    $class->created_at = $class->freshTimestamp();
    $class->updated_at = $class->freshTimestamp();
    $class->setRelation('foo', ['bar']);

    $clone = $class->replicate();

    $this->assertNull($clone->id);
    $this->assertNull($clone->uuid);
    $this->assertFalse($clone->exists);
    $this->assertSame('taylor', $clone->first);
    $this->assertSame('otwell', $clone->last);
    $this->assertArrayNotHasKey('created_at', $clone->getAttributes());
    $this->assertArrayNotHasKey('updated_at', $clone->getAttributes());
    $this->assertEquals(['bar'], $clone->foo);
});

test('clone model makes a fresh copy of the model when model has ulid primary key', function () {
    $class = new InstrumentPrimaryUlidModelStub();
    $class->ulid = '01HBZ975D8606P6CV672KW1AP2';
    $class->exists = true;
    $class->first = 'taylor';
    $class->last = 'otwell';
    $class->created_at = $class->freshTimestamp();
    $class->updated_at = $class->freshTimestamp();
    $class->setRelation('foo', ['bar']);

    $clone = $class->replicate();

    $this->assertNull($clone->ulid);
    $this->assertFalse($clone->exists);
    $this->assertSame('taylor', $clone->first);
    $this->assertSame('otwell', $clone->last);
    $this->assertArrayNotHasKey('created_at', $clone->getAttributes());
    $this->assertArrayNotHasKey('updated_at', $clone->getAttributes());
    $this->assertEquals(['bar'], $clone->foo);
});

test('clone model makes a fresh copy of the model when model has ulid', function () {
    $class = new InstrumentNonPrimaryUlidModelStub();
    $class->id = 1;
    $class->ulid = '01HBZ975D8606P6CV672KW1AP2';
    $class->exists = true;
    $class->first = 'taylor';
    $class->last = 'otwell';
    $class->created_at = $class->freshTimestamp();
    $class->updated_at = $class->freshTimestamp();
    $class->setRelation('foo', ['bar']);

    $clone = $class->replicate();

    $this->assertNull($clone->id);
    $this->assertNull($clone->ulid);
    $this->assertFalse($clone->exists);
    $this->assertSame('taylor', $clone->first);
    $this->assertSame('otwell', $clone->last);
    $this->assertArrayNotHasKey('created_at', $clone->getAttributes());
    $this->assertArrayNotHasKey('updated_at', $clone->getAttributes());
    $this->assertEquals(['bar'], $clone->foo);
});

test('model observers can be attached to models', function () {
    InstrumentModelStub::setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelStub', InstrumentTestObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelStub', InstrumentTestObserverStub::class.'@saved');
    $events->shouldReceive('forget');
    InstrumentModelStub::observe(new InstrumentTestObserverStub);
    InstrumentModelStub::flushEventListeners();
});

test('model observers can be attached to models with string', function () {
    InstrumentModelStub::setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelStub', InstrumentTestObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelStub', InstrumentTestObserverStub::class.'@saved');
    $events->shouldReceive('forget');
    InstrumentModelStub::observe(InstrumentTestObserverStub::class);
    InstrumentModelStub::flushEventListeners();
});

test('model observers can be attached to models through an array', function () {
    InstrumentModelStub::setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelStub', InstrumentTestObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelStub', InstrumentTestObserverStub::class.'@saved');
    $events->shouldReceive('forget');
    InstrumentModelStub::observe([InstrumentTestObserverStub::class]);
    InstrumentModelStub::flushEventListeners();
});

test('model observers can be attached to models with string using attribute', function () {
    InstrumentModelWithObserveAttributeStub::setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch');
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelWithObserveAttributeStub', InstrumentTestObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelWithObserveAttributeStub', InstrumentTestObserverStub::class.'@saved');
    $events->shouldReceive('forget');
    InstrumentModelWithObserveAttributeStub::flushEventListeners();
});

test('model observers can be attached to models through an array using attribute', function () {
    InstrumentModelWithObserveAttributeUsingArrayStub::setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch');
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelWithObserveAttributeUsingArrayStub', InstrumentTestObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelWithObserveAttributeUsingArrayStub', InstrumentTestObserverStub::class.'@saved');
    $events->shouldReceive('forget');
    InstrumentModelWithObserveAttributeUsingArrayStub::flushEventListeners();
});

test('model observers can be attached to models through attributes on parent classes', function () {
    InstrumentModelWithObserveAttributeGrandchildStub::setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch');
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelWithObserveAttributeGrandchildStub', InstrumentTestObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelWithObserveAttributeGrandchildStub', InstrumentTestObserverStub::class.'@saved');
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelWithObserveAttributeGrandchildStub', InstrumentTestAnotherObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelWithObserveAttributeGrandchildStub', InstrumentTestAnotherObserverStub::class.'@saved');
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelWithObserveAttributeGrandchildStub', InstrumentTestThirdObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelWithObserveAttributeGrandchildStub', InstrumentTestThirdObserverStub::class.'@saved');
    $events->shouldReceive('forget');
    InstrumentModelWithObserveAttributeGrandchildStub::flushEventListeners();
});

test('throw exception on attaching not exists model observer with string', function () {
    InstrumentModelStub::observe(NotExistClass::class);
})->throws(InvalidArgumentException::class);

test('throw exception on attaching not exists model observers through an array', function () {
    InstrumentModelStub::observe([NotExistClass::class]);
})->throws(InvalidArgumentException::class);

test('model observers can be attached to models through calling observe method only once', function () {
    InstrumentModelStub::setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelStub', InstrumentTestObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelStub', InstrumentTestObserverStub::class.'@saved');

    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelStub', InstrumentTestAnotherObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelStub', InstrumentTestAnotherObserverStub::class.'@saved');

    $events->shouldReceive('forget');

    InstrumentModelStub::observe([
        InstrumentTestObserverStub::class,
        InstrumentTestAnotherObserverStub::class,
    ]);

    InstrumentModelStub::flushEventListeners();
});

test('without event dispatcher', function () {
    InstrumentModelSaveStub::setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('listen')->once()->with('instrument.creating: Tests\Database\InstrumentModelSaveStub', InstrumentTestObserverStub::class.'@creating');
    $events->shouldReceive('listen')->once()->with('instrument.saved: Tests\Database\InstrumentModelSaveStub', InstrumentTestObserverStub::class.'@saved');
    $events->shouldNotReceive('until');
    $events->shouldNotReceive('dispatch');
    $events->shouldReceive('forget');
    InstrumentModelSaveStub::observe(InstrumentTestObserverStub::class);

    $model = InstrumentModelSaveStub::withoutEvents(function () {
        $model = new InstrumentModelSaveStub;
        $model->save();

        return $model;
    });

    $model->withoutEvents(function () use ($model) {
        $model->first_name = 'Taylor';
        $model->save();
    });

    $events->shouldReceive('until')->once()->with('instrument.saving: Tests\Database\InstrumentModelSaveStub', $model);
    $events->shouldReceive('dispatch')->once()->with('instrument.saved: Tests\Database\InstrumentModelSaveStub', $model);

    $model->last_name = 'Otwell';
    $model->save();

    InstrumentModelSaveStub::flushEventListeners();
});

test('set observable events', function () {
    $class = new InstrumentModelStub;
    $class->setObservableEvents(['foo']);

    $this->assertContains('foo', $class->getObservableEvents());
});

test('add observable event', function () {
    $class = new InstrumentModelStub;
    $class->addObservableEvents('foo');

    $this->assertContains('foo', $class->getObservableEvents());
});

test('add multiple observeable events', function () {
    $class = new InstrumentModelStub;
    $class->addObservableEvents('foo', 'bar');

    $this->assertContains('foo', $class->getObservableEvents());
    $this->assertContains('bar', $class->getObservableEvents());
});

test('remove observable event', function () {
    $class = new InstrumentModelStub;
    $class->setObservableEvents(['foo', 'bar']);
    $class->removeObservableEvents('bar');

    $this->assertNotContains('bar', $class->getObservableEvents());
});

test('remove multiple observable events', function () {
    $class = new InstrumentModelStub;
    $class->setObservableEvents(['foo', 'bar']);
    $class->removeObservableEvents('foo', 'bar');

    $this->assertNotContains('foo', $class->getObservableEvents());
    $this->assertNotContains('bar', $class->getObservableEvents());
});

test('get model attribute method throws exception if not relation', function () {
    $model = new InstrumentModelStub;
    $model->incorrectRelationStub;
})->throws(LogicException::class, 'Tests\Database\InstrumentModelStub::incorrectRelationStub must return a relationship instance.');

test('model is booted on unserialize', function () {
    $model = new InstrumentModelBootingTestStub;
    $this->assertTrue(InstrumentModelBootingTestStub::isBooted());
    $model->foo = 'bar';
    $string = serialize($model);
    $model = null;
    InstrumentModelBootingTestStub::unboot();
    $this->assertFalse(InstrumentModelBootingTestStub::isBooted());
    unserialize($string);
    $this->assertTrue(InstrumentModelBootingTestStub::isBooted());
});

test('callbacks can be run after booting has finished', function () {
    $this->assertFalse(InstrumentModelBootingCallbackTestStub::$bootHasFinished);

    $model = new InstrumentModelBootingCallbackTestStub();

    $this->assertTrue($model::$bootHasFinished);

    InstrumentModelBootingCallbackTestStub::unboot();
});

test('booted callbacks are separated by class', function () {
    $this->assertFalse(InstrumentModelBootingCallbackTestStub::$bootHasFinished);

    $model = new InstrumentModelBootingCallbackTestStub();

    $this->assertTrue($model::$bootHasFinished);

    $this->assertFalse(InstrumentChildModelBootingCallbackTestStub::$bootHasFinished);

    $model = new InstrumentChildModelBootingCallbackTestStub();

    $this->assertTrue($model::$bootHasFinished);

    InstrumentModelBootingCallbackTestStub::unboot();
    InstrumentChildModelBootingCallbackTestStub::unboot();
});

test('models trait is initialized', function () {
    $model = new InstrumentModelStubWithTrait;
    $this->assertTrue($model->fooBarIsInitialized);
});

test('appending of attributes', function () {
    $model = new InstrumentModelAppendsStub;

    $this->assertTrue(isset($model->is_admin));
    $this->assertTrue(isset($model->camelCased));
    $this->assertTrue(isset($model->StudlyCased));

    $this->assertSame('admin', $model->is_admin);
    $this->assertSame('camelCased', $model->camelCased);
    $this->assertSame('StudlyCased', $model->StudlyCased);

    $this->assertEquals(['is_admin', 'camelCased', 'StudlyCased'], $model->getAppends());

    $this->assertTrue($model->hasAppended('is_admin'));
    $this->assertTrue($model->hasAppended('camelCased'));
    $this->assertTrue($model->hasAppended('StudlyCased'));
    $this->assertFalse($model->hasAppended('not_appended'));

    $model->setHidden(['is_admin', 'camelCased', 'StudlyCased']);
    $this->assertEquals([], $model->toArray());

    $model->setVisible([]);
    $this->assertEquals([], $model->toArray());
});

test('merge appends merges appends', function () {
    $model = new InstrumentModelAppendsStub;

    $appendsCount = count($model->getAppends());
    $this->assertEquals(['is_admin', 'camelCased', 'StudlyCased'], $model->getAppends());

    $model->mergeAppends(['bar']);
    $this->assertCount($appendsCount + 1, $model->getAppends());
    $this->assertContains('bar', $model->getAppends());
});

test('has appended returns true when attribute is appended', function () {
    $model = new InstrumentModelAppendsStub;

    $this->assertTrue($model->hasAppended('is_admin'));
    $this->assertTrue($model->hasAppended('camelCased'));
    $this->assertTrue($model->hasAppended('StudlyCased'));
});

test('has appended returns false when attribute is not appended', function () {
    $model = new InstrumentModelAppendsStub;

    $this->assertFalse($model->hasAppended('foo'));
    $this->assertFalse($model->hasAppended('bar'));
});

test('without appends removes appends', function () {
    $model = new InstrumentModelAppendsStub;

    $this->assertEquals(['is_admin', 'camelCased', 'StudlyCased'], $model->getAppends());

    $model->withoutAppends();

    $this->assertEmpty($model->getAppends());
});

test('get mutated attributes', function () {
    $model = new InstrumentModelGetMutatorsStub;

    $this->assertEquals(['first_name', 'middle_name', 'last_name'], $model->getMutatedAttributes());

    InstrumentModelGetMutatorsStub::resetMutatorCache();

    InstrumentModelGetMutatorsStub::$snakeAttributes = false;
    $this->assertEquals(['firstName', 'middleName', 'lastName'], $model->getMutatedAttributes());
});

test('replicate creates a new model instance with same attribute values', function () {
    $model = new InstrumentModelStub;
    $model->id = 'id';
    $model->foo = 'bar';
    $model->created_at = new DateTime;
    $model->updated_at = new DateTime;
    $replicated = $model->replicate();

    $this->assertNull($replicated->id);
    $this->assertSame('bar', $replicated->foo);
    $this->assertNull($replicated->created_at);
    $this->assertNull($replicated->updated_at);
});

test('replicating event is fired when replicating model', function () {
    $model = new InstrumentModelStub;

    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->once()->with('instrument.replicating: '.get_class($model), m::on(function ($m) use ($model) {
        return $model->is($m);
    }));

    $model->replicate();
});

test('replicate quietly creates a new model instance with same attribute values and is quiet', function () {
    $model = new InstrumentModelStub;
    $model->id = 'id';
    $model->foo = 'bar';
    $model->created_at = new DateTime;
    $model->updated_at = new DateTime;
    $replicated = $model->replicateQuietly();

    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('dispatch')->never()->with('instrument.replicating: '.get_class($model), $model)->andReturn(true);

    $this->assertNull($replicated->id);
    $this->assertSame('bar', $replicated->foo);
    $this->assertNull($replicated->created_at);
    $this->assertNull($replicated->updated_at);
});

test('increment on existing model calls query and sets attribute', function () {
    $model = m::mock(InstrumentModelStub::class.'[newQueryWithoutScopes]');
    $model->exists = true;
    $model->id = 1;
    $model->syncOriginalAttribute('id');
    $model->foo = 2;

    $model->shouldReceive('newQueryWithoutScopes')->andReturn($query = m::mock(stdClass::class));
    $query->shouldReceive('where')->andReturn($query);
    $query->shouldReceive('increment');

    // hmm
    $model->publicIncrement('foo', 1);
    $this->assertFalse($model->isDirty());

    $model->publicIncrement('foo', 1, ['category' => 1]);
    $this->assertEquals(4, $model->foo);
    $this->assertEquals(1, $model->category);
    $this->assertTrue($model->isDirty('category'));
});

test('increment quietly on existing model calls query and sets attribute and is quiet', function () {
    $model = m::mock(InstrumentModelStub::class.'[newQueryWithoutScopes]');
    $model->exists = true;
    $model->id = 1;
    $model->syncOriginalAttribute('id');
    $model->foo = 2;

    $model->shouldReceive('newQueryWithoutScopes')->andReturn($query = m::mock(stdClass::class));
    $query->shouldReceive('where')->andReturn($query);
    $query->shouldReceive('increment');

    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->never()->with('instrument.saving: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('until')->never()->with('instrument.updating: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->never()->with('instrument.updated: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->never()->with('instrument.saved: '.get_class($model), $model)->andReturn(true);

    $model->publicIncrementQuietly('foo', 1);
    $this->assertFalse($model->isDirty());

    $model->publicIncrementQuietly('foo', 1, ['category' => 1]);
    $this->assertEquals(4, $model->foo);
    $this->assertEquals(1, $model->category);
    $this->assertTrue($model->isDirty('category'));
});

test('decrement quietly on existing model calls query and sets attribute and is quiet', function () {
    $model = m::mock(InstrumentModelStub::class.'[newQueryWithoutScopes]');
    $model->exists = true;
    $model->id = 1;
    $model->syncOriginalAttribute('id');
    $model->foo = 4;

    $model->shouldReceive('newQueryWithoutScopes')->andReturn($query = m::mock(stdClass::class));
    $query->shouldReceive('where')->andReturn($query);
    $query->shouldReceive('decrement');

    $model->setEventDispatcher($events = m::mock(Dispatcher::class));
    $events->shouldReceive('until')->never()->with('instrument.saving: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('until')->never()->with('instrument.updating: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->never()->with('instrument.updated: '.get_class($model), $model)->andReturn(true);
    $events->shouldReceive('dispatch')->never()->with('instrument.saved: '.get_class($model), $model)->andReturn(true);

    $model->publicDecrementQuietly('foo', 1);
    $this->assertFalse($model->isDirty());

    $model->publicDecrementQuietly('foo', 1, ['category' => 1]);
    $this->assertEquals(2, $model->foo);
    $this->assertEquals(1, $model->category);
    $this->assertTrue($model->isDirty('category'));
});

test('relationship touch owners is propagated', function () {
    $relation = $this->getMockBuilder(BelongsTo::class)->onlyMethods(['touch'])->disableOriginalConstructor()->getMock();
    $relation->expects($this->once())->method('touch');

    $model = m::mock(InstrumentModelStub::class.'[partner]');
    modelAddMockConnection($model);
    $model->shouldReceive('partner')->once()->andReturn($relation);
    $model->setTouchedRelations(['partner']);

    $mockPartnerModel = m::mock(InstrumentModelStub::class.'[touchOwners]');
    $mockPartnerModel->shouldReceive('touchOwners')->once();
    $model->setRelation('partner', $mockPartnerModel);

    $model->touchOwners();
});

test('relationship touch owners is not propagated if no relationship result', function () {
    $relation = $this->getMockBuilder(BelongsTo::class)->onlyMethods(['touch'])->disableOriginalConstructor()->getMock();
    $relation->expects($this->once())->method('touch');

    $model = m::mock(InstrumentModelStub::class.'[partner]');
    modelAddMockConnection($model);
    $model->shouldReceive('partner')->once()->andReturn($relation);
    $model->setTouchedRelations(['partner']);

    $model->setRelation('partner', null);

    $model->touchOwners();
});

test('model attributes are casted when present in casts property or casts method', function () {
    $model = new InstrumentModelCastingStub;
    $model->setDateFormat('Y-m-d H:i:s');
    $model->intAttribute = '3';
    $model->floatAttribute = '4.0';
    $model->stringAttribute = 2.5;
    $model->boolAttribute = 1;
    $model->booleanAttribute = 0;
    $model->objectAttribute = ['foo' => 'bar'];
    $obj = new stdClass;
    $obj->foo = 'bar';
    $model->arrayAttribute = $obj;
    $model->jsonAttribute = ['foo' => 'bar'];
    $model->jsonAttributeWithUnicode = ['こんにちは' => '世界'];
    $model->dateAttribute = '1969-07-20';
    $model->datetimeAttribute = '1969-07-20 22:56:00';
    $model->timestampAttribute = '1969-07-20 22:56:00';
    $model->collectionAttribute = new BaseCollection;
    $model->asCustomCollectionAttribute = new CustomCollection;

    $this->assertIsInt($model->intAttribute);
    $this->assertIsFloat($model->floatAttribute);
    $this->assertIsString($model->stringAttribute);
    $this->assertIsBool($model->boolAttribute);
    $this->assertIsBool($model->booleanAttribute);
    $this->assertIsObject($model->objectAttribute);
    $this->assertIsArray($model->arrayAttribute);
    $this->assertIsArray($model->jsonAttribute);
    $this->assertIsArray($model->jsonAttributeWithUnicode);
    $this->assertTrue($model->boolAttribute);
    $this->assertFalse($model->booleanAttribute);
    $this->assertEquals($obj, $model->objectAttribute);
    $this->assertEquals(['foo' => 'bar'], $model->arrayAttribute);
    $this->assertEquals(['foo' => 'bar'], $model->jsonAttribute);
    $this->assertSame('{"foo":"bar"}', $model->jsonAttributeValue());
    $this->assertEquals(['こんにちは' => '世界'], $model->jsonAttributeWithUnicode);
    $this->assertSame('{"こんにちは":"世界"}', $model->jsonAttributeWithUnicodeValue());
    $this->assertInstanceOf(Carbon::class, $model->dateAttribute);
    $this->assertInstanceOf(Carbon::class, $model->datetimeAttribute);
    $this->assertInstanceOf(BaseCollection::class, $model->collectionAttribute);
    $this->assertInstanceOf(CustomCollection::class, $model->asCustomCollectionAttribute);
    $this->assertSame('1969-07-20', $model->dateAttribute->toDateString());
    $this->assertSame('1969-07-20 22:56:00', $model->datetimeAttribute->toDateTimeString());
    $this->assertEquals(-14173440, $model->timestampAttribute);

    $arr = $model->toArray();

    $this->assertIsInt($arr['intAttribute']);
    $this->assertIsFloat($arr['floatAttribute']);
    $this->assertIsString($arr['stringAttribute']);
    $this->assertIsBool($arr['boolAttribute']);
    $this->assertIsBool($arr['booleanAttribute']);
    $this->assertIsObject($arr['objectAttribute']);
    $this->assertIsArray($arr['arrayAttribute']);
    $this->assertIsArray($arr['jsonAttribute']);
    $this->assertIsArray($arr['jsonAttributeWithUnicode']);
    $this->assertIsArray($arr['collectionAttribute']);
    $this->assertTrue($arr['boolAttribute']);
    $this->assertFalse($arr['booleanAttribute']);
    $this->assertEquals($obj, $arr['objectAttribute']);
    $this->assertEquals(['foo' => 'bar'], $arr['arrayAttribute']);
    $this->assertEquals(['foo' => 'bar'], $arr['jsonAttribute']);
    $this->assertEquals(['こんにちは' => '世界'], $arr['jsonAttributeWithUnicode']);
    $this->assertSame('1969-07-20 00:00:00', $arr['dateAttribute']);
    $this->assertSame('1969-07-20 22:56:00', $arr['datetimeAttribute']);
    $this->assertEquals(-14173440, $arr['timestampAttribute']);
});

test('model date attribute casting resets time', function () {
    $model = new InstrumentModelCastingStub;
    $model->setDateFormat('Y-m-d H:i:s');
    $model->dateAttribute = '1969-07-20 22:56:00';

    $this->assertSame('1969-07-20 00:00:00', $model->dateAttribute->toDateTimeString());

    $arr = $model->toArray();
    $this->assertSame('1969-07-20 00:00:00', $arr['dateAttribute']);
});

test('model attribute casting preserves null', function () {
    $model = new InstrumentModelCastingStub;
    $model->intAttribute = null;
    $model->floatAttribute = null;
    $model->stringAttribute = null;
    $model->boolAttribute = null;
    $model->booleanAttribute = null;
    $model->objectAttribute = null;
    $model->arrayAttribute = null;
    $model->jsonAttribute = null;
    $model->jsonAttributeWithUnicode = null;
    $model->dateAttribute = null;
    $model->datetimeAttribute = null;
    $model->timestampAttribute = null;
    $model->collectionAttribute = null;

    $attributes = $model->getAttributes();

    $this->assertNull($attributes['intAttribute']);
    $this->assertNull($attributes['floatAttribute']);
    $this->assertNull($attributes['stringAttribute']);
    $this->assertNull($attributes['boolAttribute']);
    $this->assertNull($attributes['booleanAttribute']);
    $this->assertNull($attributes['objectAttribute']);
    $this->assertNull($attributes['arrayAttribute']);
    $this->assertNull($attributes['jsonAttribute']);
    $this->assertNull($attributes['jsonAttributeWithUnicode']);
    $this->assertNull($attributes['dateAttribute']);
    $this->assertNull($attributes['datetimeAttribute']);
    $this->assertNull($attributes['timestampAttribute']);
    $this->assertNull($attributes['collectionAttribute']);

    $this->assertNull($model->intAttribute);
    $this->assertNull($model->floatAttribute);
    $this->assertNull($model->stringAttribute);
    $this->assertNull($model->boolAttribute);
    $this->assertNull($model->booleanAttribute);
    $this->assertNull($model->objectAttribute);
    $this->assertNull($model->arrayAttribute);
    $this->assertNull($model->jsonAttribute);
    $this->assertNull($model->jsonAttributeWithUnicode);
    $this->assertNull($model->dateAttribute);
    $this->assertNull($model->datetimeAttribute);
    $this->assertNull($model->timestampAttribute);
    $this->assertNull($model->collectionAttribute);

    $array = $model->toArray();

    $this->assertNull($array['intAttribute']);
    $this->assertNull($array['floatAttribute']);
    $this->assertNull($array['stringAttribute']);
    $this->assertNull($array['boolAttribute']);
    $this->assertNull($array['booleanAttribute']);
    $this->assertNull($array['objectAttribute']);
    $this->assertNull($array['arrayAttribute']);
    $this->assertNull($array['jsonAttribute']);
    $this->assertNull($array['jsonAttributeWithUnicode']);
    $this->assertNull($array['dateAttribute']);
    $this->assertNull($array['datetimeAttribute']);
    $this->assertNull($array['timestampAttribute']);
    $this->assertNull($attributes['collectionAttribute']);
});

test('model attribute casting fails on unencodable data', function () {
    $model = new InstrumentModelCastingStub;
    $model->objectAttribute = ['foo' => "b\xF8r"];
    $obj = new stdClass;
    $obj->foo = "b\xF8r";
    $model->arrayAttribute = $obj;

    $model->getAttributes();
})->throws(JsonEncodingException::class, 'Unable to encode attribute [objectAttribute] for model [Tests\Database\InstrumentModelCastingStub] to JSON: Malformed UTF-8 characters, possibly incorrectly encoded.');

test('model json casting fails on unencodable data', function () {
    $model = new InstrumentModelCastingStub;
    $model->jsonAttribute = ['foo' => "b\xF8r"];

    $model->getAttributes();
})->throws(JsonEncodingException::class, 'Unable to encode attribute [jsonAttribute] for model [Tests\Database\InstrumentModelCastingStub] to JSON: Malformed UTF-8 characters, possibly incorrectly encoded.');

test('model attribute casting fails on unencodable data with unicode', function () {
    $model = new InstrumentModelCastingStub;
    $model->jsonAttributeWithUnicode = ['foo' => "b\xF8r"];

    $model->getAttributes();
})->throws(JsonEncodingException::class, 'Unable to encode attribute [jsonAttributeWithUnicode] for model [Tests\Database\InstrumentModelCastingStub] to JSON: Malformed UTF-8 characters, possibly incorrectly encoded.');

test('json casting respects unicode option', function () {
    $data = ['こんにちは' => '世界'];
    $model = new InstrumentModelCastingStub;
    $model->jsonAttribute = $data;
    $model->jsonAttributeWithUnicode = $data;

    $this->assertSame('{"\u3053\u3093\u306b\u3061\u306f":"\u4e16\u754c"}', $model->jsonAttributeValue());
    $this->assertSame('{"こんにちは":"世界"}', $model->jsonAttributeWithUnicodeValue());
    $this->assertSame(['こんにちは' => '世界'], $model->jsonAttribute);
    $this->assertSame(['こんにちは' => '世界'], $model->jsonAttributeWithUnicode);
});

test('model attribute casting with floats', function () {
    $model = new InstrumentModelCastingStub;

    $model->floatAttribute = 0;
    $this->assertSame(0.0, $model->floatAttribute);

    $model->floatAttribute = 'Infinity';
    $this->assertSame(INF, $model->floatAttribute);

    $model->floatAttribute = INF;
    $this->assertSame(INF, $model->floatAttribute);

    $model->floatAttribute = '-Infinity';
    $this->assertSame(-INF, $model->floatAttribute);

    $model->floatAttribute = -INF;
    $this->assertSame(-INF, $model->floatAttribute);

    $model->floatAttribute = 'NaN';
    $this->assertNan($model->floatAttribute);

    $model->floatAttribute = NAN;
    $this->assertNan($model->floatAttribute);
});

test('model attribute casting with arrays', function () {
    $model = new InstrumentModelCastingStub;

    $model->asEnumArrayObjectAttribute = ['draft', 'pending'];
    $this->assertInstanceOf(ArrayObject::class, $model->asEnumArrayObjectAttribute);
});

test('merge casts merges casts', function () {
    $model = new InstrumentModelCastingStub;

    $castCount = count($model->getCasts());
    $this->assertArrayNotHasKey('foo', $model->getCasts());

    $model->mergeCasts(['foo' => 'date']);
    $this->assertCount($castCount + 1, $model->getCasts());
    $this->assertArrayHasKey('foo', $model->getCasts());
});

test('merge casts merges casts using arrays', function () {
    $model = new InstrumentModelCastingStub;

    $castCount = count($model->getCasts());
    $this->assertArrayNotHasKey('foo', $model->getCasts());

    $model->mergeCasts([
        'foo' => ['MyClass', 'myArgumentA'],
        'bar' => ['MyClass', 'myArgumentA', 'myArgumentB'],
    ]);

    $this->assertCount($castCount + 2, $model->getCasts());
    $this->assertArrayHasKey('foo', $model->getCasts());
    $this->assertEquals($model->getCasts()['foo'], 'MyClass:myArgumentA');
    $this->assertEquals($model->getCasts()['bar'], 'MyClass:myArgumentA,myArgumentB');
});

test('unset cast attributes', function () {
    $model = new InstrumentModelCastingStub;
    $model->asToObjectCast = TestValueObject::make([
        'myPropertyA' => 'A',
        'myPropertyB' => 'B',
    ]);
    unset($model->asToObjectCast);
    $this->assertArrayNotHasKey('asToObjectCast', $model->getAttributes());
});

test('updating non existent model fails', function () {
    $model = new InstrumentModelStub;
    $this->assertFalse($model->update());
});

test('isset behaves correctly with attributes and relationships', function () {
    $model = new InstrumentModelStub;
    $this->assertFalse(isset($model->nonexistent));

    $model->some_attribute = 'some_value';
    $this->assertTrue(isset($model->some_attribute));

    $model->setRelation('some_relation', 'some_value');
    $this->assertTrue(isset($model->some_relation));
});

test('non existing attribute with internal method name doesnt call method', function () {
    $model = m::mock(InstrumentModelStub::class.'[delete,getRelationValue]');
    $model->name = 'Spark';
    $model->shouldNotReceive('delete');
    $model->shouldReceive('getRelationValue')->once()->with('belongsToStub')->andReturn('relation');

    // Can return a normal relation
    $this->assertSame('relation', $model->belongsToStub);

    // Can return a normal attribute
    $this->assertSame('Spark', $model->name);

    // Returns null for a Model.php method name
    $this->assertNull($model->delete);

    $model = m::mock(InstrumentModelStub::class.'[delete]');
    $model->delete = 123;
    $this->assertEquals(123, $model->delete);
});

test('int key type preserved', function () {
    $model = $this->getMockBuilder(InstrumentModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();
    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with([], 'id')->andReturn(1);
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);

    $this->assertTrue($model->save());
    $this->assertEquals(1, $model->id);
});

test('string key type preserved', function () {
    $model = $this->getMockBuilder(InstrumentKeyTypeModelStub::class)->onlyMethods(['newModelQuery', 'updateTimestamps', 'refresh'])->getMock();

    $query = m::mock(Builder::class);
    $query->shouldReceive('insertGetId')->once()->with([], 'id')->andReturn('string id');
    $query->shouldReceive('getConnection')->once();
    $model->expects($this->once())->method('newModelQuery')->willReturn($query);

    $this->assertTrue($model->save());
    $this->assertSame('string id', $model->id);
});

test('scopes method', function () {
    $model = new InstrumentModelStub;
    modelAddMockConnection($model);

    $scopes = [
        'published',
        'category' => 'Laravel',
        'framework' => ['Laravel', '5.3'],
        'date' => Carbon::now(),
    ];

    $this->assertInstanceOf(Builder::class, $model->scopes($scopes));
    $this->assertSame($scopes, $model->scopesCalled);
});

test('scopes method with string', function () {
    $model = new InstrumentModelStub;
    modelAddMockConnection($model);

    $this->assertInstanceOf(Builder::class, $model->scopes('published'));
    $this->assertSame(['published'], $model->scopesCalled);
});

test('is with null', function () {
    $firstInstance = new InstrumentModelStub(['id' => 1]);
    $secondInstance = null;

    $this->assertFalse($firstInstance->is($secondInstance));
});

test('is with the same model instance', function () {
    $firstInstance = new InstrumentModelStub(['id' => 1]);
    $secondInstance = new InstrumentModelStub(['id' => 1]);
    $result = $firstInstance->is($secondInstance);
    $this->assertTrue($result);
});

test('is with another model instance', function () {
    $firstInstance = new InstrumentModelStub(['id' => 1]);
    $secondInstance = new InstrumentModelStub(['id' => 2]);
    $result = $firstInstance->is($secondInstance);
    $this->assertFalse($result);
});

test('is with another table', function () {
    $firstInstance = new InstrumentModelStub(['id' => 1]);
    $secondInstance = new InstrumentModelStub(['id' => 1]);
    $secondInstance->setTable('foo');
    $result = $firstInstance->is($secondInstance);
    $this->assertFalse($result);
});

test('is with another connection', function () {
    $firstInstance = new InstrumentModelStub(['id' => 1]);
    $secondInstance = new InstrumentModelStub(['id' => 1]);
    $secondInstance->setConnection('foo');
    $result = $firstInstance->is($secondInstance);
    $this->assertFalse($result);
});

test('without touching callback', function () {
    new InstrumentModelStub(['id' => 1]);

    $called = false;

    InstrumentModelStub::withoutTouching(function () use (&$called) {
        $called = true;
    });

    $this->assertTrue($called);
});

test('without touching on callback', function () {
    new InstrumentModelStub(['id' => 1]);

    $called = false;

    Model::withoutTouchingOn([InstrumentModelStub::class], function () use (&$called) {
        $called = true;
    });

    $this->assertTrue($called);
});

test('throws when accessing missing attributes', function () {
    $originalMode = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $model = new InstrumentModelStub(['id' => 1]);
        $model->exists = true;

        $this->assertEquals(1, $model->id);

        $model->this_attribute_does_not_exist;
    } finally {
        Model::preventAccessingMissingAttributes($originalMode);
    }
})->throws(MissingAttributeException::class);

test('throws when accessing missing attributes which are primitive casts', function () {
    $originalMode = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    $model = new InstrumentModelWithPrimitiveCasts(['id' => 1]);
    $model->exists = true;

    $exceptionCount = 0;
    $primitiveCasts = InstrumentModelWithPrimitiveCasts::makePrimitiveCastsArray();
    try {
        try {
            $this->assertEquals(null, $model->backed_enum);
        } catch (MissingAttributeException) {
            $exceptionCount++;
        }

        foreach ($primitiveCasts as $key => $type) {
            try {
                $v = $model->{$key};
            } catch (MissingAttributeException) {
                $exceptionCount++;
            }
        }

        $this->assertInstanceOf(Address::class, $model->address);

        $this->assertEquals(1, $model->id);
        $this->assertEquals('ok', $model->this_is_fine);
        $this->assertEquals('ok', $model->this_is_also_fine);

        // Primitive castables, enum castable
        $expectedExceptionCount = count($primitiveCasts) + 1;
        $this->assertEquals($expectedExceptionCount, $exceptionCount);
    } finally {
        Model::preventAccessingMissingAttributes($originalMode);
    }
});

test('uses overridden handler when accessing missing attributes', function () {
    $originalMode = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    $callbackModel = null;
    $callbackKey = null;

    Model::handleMissingAttributeViolationUsing(function ($model, $key) use (&$callbackModel, &$callbackKey) {
        $callbackModel = $model;
        $callbackKey = $key;
    });

    $model = new InstrumentModelStub(['id' => 1]);
    $model->exists = true;

    $this->assertEquals(1, $model->id);

    $model->this_attribute_does_not_exist;

    $this->assertInstanceOf(InstrumentModelStub::class, $callbackModel);
    $this->assertEquals('this_attribute_does_not_exist', $callbackKey);

    Model::preventAccessingMissingAttributes($originalMode);
    Model::handleMissingAttributeViolationUsing(null);
});

test('doesnt throw when accessing missing attributes on model that is not saved', function () {
    $originalMode = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $model = new InstrumentModelStub(['id' => 1]);
        $model->exists = false;

        $this->assertEquals(1, $model->id);
        $this->assertNull($model->this_attribute_does_not_exist);
    } finally {
        Model::preventAccessingMissingAttributes($originalMode);
    }
});

test('doesnt throw when accessing missing attributes on model that was recently created', function () {
    $originalMode = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $model = new InstrumentModelStub(['id' => 1]);
        $model->exists = true;
        $model->wasRecentlyCreated = true;

        $this->assertEquals(1, $model->id);
        $this->assertNull($model->this_attribute_does_not_exist);
    } finally {
        Model::preventAccessingMissingAttributes($originalMode);
    }
});

test('doesnt throw when assigning missing attributes', function () {
    $this->expectNotToPerformAssertions();

    $originalMode = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $model = new InstrumentModelStub(['id' => 1]);
        $model->exists = true;

        $model->this_attribute_does_not_exist = 'now it does';
    } finally {
        Model::preventAccessingMissingAttributes($originalMode);
    }
});

test('doesnt throw when testing missing attributes', function () {
    $originalMode = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $model = new InstrumentModelStub(['id' => 1]);
        $model->exists = true;

        $this->assertTrue(isset($model->id));
        $this->assertFalse(isset($model->this_attribute_does_not_exist));
    } finally {
        Model::preventAccessingMissingAttributes($originalMode);
    }
});

test('touch method with multiple attributes', function () {
    Carbon::setTestNow($now = Carbon::now());

    $model = m::mock(InstrumentModelStub::class.'[save]');
    $model->shouldReceive('save')->once()->andReturn(true);

    $result = $model->touch(['published_at', 'verified_at']);

    $this->assertTrue($result);
    $this->assertEquals($now->toDateTimeString(), $model->published_at->toDateTimeString());
    $this->assertEquals($now->toDateTimeString(), $model->verified_at->toDateTimeString());
});

test('touching model with timestamps', function () {
    $this->assertFalse(
        Model::isIgnoringTouch(Model::class)
    );
});

test('not touching model with updated at null', function () {
    $this->assertTrue(
        Model::isIgnoringTouch(InstrumentModelWithUpdatedAtNull::class)
    );
});

test('not touching model without timestamps', function () {
    $this->assertTrue(
        Model::isIgnoringTouch(InstrumentModelWithoutTimestamps::class)
    );
});

test('get original casts attributes', function () {
    $model = new InstrumentModelCastingStub;
    $model->intAttribute = '1';
    $model->floatAttribute = '0.1234';
    $model->stringAttribute = 432;
    $model->boolAttribute = '1';
    $model->booleanAttribute = '0';
    $stdClass = new stdClass;
    $stdClass->json_key = 'json_value';
    $model->objectAttribute = $stdClass;
    $array = [
        'foo' => 'bar',
    ];
    $collection = collect($array);
    $model->arrayAttribute = $array;
    $model->jsonAttribute = $array;
    $model->jsonAttributeWithUnicode = $array;
    $model->collectionAttribute = $collection;

    $model->syncOriginal();

    $model->intAttribute = 2;
    $model->floatAttribute = 0.443;
    $model->stringAttribute = '12';
    $model->boolAttribute = true;
    $model->booleanAttribute = false;
    $model->objectAttribute = $stdClass;
    $model->arrayAttribute = [
        'foo' => 'bar2',
    ];
    $model->jsonAttribute = [
        'foo' => 'bar2',
    ];
    $model->jsonAttributeWithUnicode = [
        'foo' => 'bar2',
    ];
    $model->collectionAttribute = collect([
        'foo' => 'bar2',
    ]);

    $this->assertIsInt($model->getOriginal('intAttribute'));
    $this->assertEquals(1, $model->getOriginal('intAttribute'));
    $this->assertEquals(2, $model->intAttribute);
    $this->assertEquals(2, $model->getAttribute('intAttribute'));

    $this->assertIsFloat($model->getOriginal('floatAttribute'));
    $this->assertEquals(0.1234, $model->getOriginal('floatAttribute'));
    $this->assertEquals(0.443, $model->floatAttribute);

    $this->assertIsString($model->getOriginal('stringAttribute'));
    $this->assertSame('432', $model->getOriginal('stringAttribute'));
    $this->assertSame('12', $model->stringAttribute);

    $this->assertIsBool($model->getOriginal('boolAttribute'));
    $this->assertTrue($model->getOriginal('boolAttribute'));
    $this->assertTrue($model->boolAttribute);

    $this->assertIsBool($model->getOriginal('booleanAttribute'));
    $this->assertFalse($model->getOriginal('booleanAttribute'));
    $this->assertFalse($model->booleanAttribute);

    $this->assertEquals($stdClass, $model->getOriginal('objectAttribute'));
    $this->assertEquals($model->getAttribute('objectAttribute'), $model->getOriginal('objectAttribute'));

    $this->assertEquals($array, $model->getOriginal('arrayAttribute'));
    $this->assertEquals(['foo' => 'bar'], $model->getOriginal('arrayAttribute'));
    $this->assertEquals(['foo' => 'bar2'], $model->getAttribute('arrayAttribute'));

    $this->assertEquals($array, $model->getOriginal('jsonAttribute'));
    $this->assertEquals(['foo' => 'bar'], $model->getOriginal('jsonAttribute'));
    $this->assertEquals(['foo' => 'bar2'], $model->getAttribute('jsonAttribute'));

    $this->assertEquals($array, $model->getOriginal('jsonAttributeWithUnicode'));
    $this->assertEquals(['foo' => 'bar'], $model->getOriginal('jsonAttributeWithUnicode'));
    $this->assertEquals(['foo' => 'bar2'], $model->getAttribute('jsonAttributeWithUnicode'));

    $this->assertEquals(['foo' => 'bar'], $model->getOriginal('collectionAttribute')->toArray());
    $this->assertEquals(['foo' => 'bar2'], $model->getAttribute('collectionAttribute')->toArray());
});

test('casts method has priority over casts property', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'duplicatedAttribute' => '1',
    ], true);

    $this->assertIsInt($model->duplicatedAttribute);
    $this->assertEquals(1, $model->duplicatedAttribute);
    $this->assertEquals(1, $model->getAttribute('duplicatedAttribute'));
});

test('casts method is taken in consideration on serialization', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'duplicatedAttribute' => '1',
    ], true);

    $model = unserialize(serialize($model));

    $this->assertIsInt($model->duplicatedAttribute);
    $this->assertEquals(1, $model->duplicatedAttribute);
    $this->assertEquals(1, $model->getAttribute('duplicatedAttribute'));
});

test('cast on array format with one element', function () {
    $model = new InstrumentModelCastingStub;
    $model->setRawAttributes([
        'singleElementInArrayAttribute' => '{"bar": "foo"}',
    ]);
    $model->syncOriginal();

    $this->assertInstanceOf(BaseCollection::class, $model->singleElementInArrayAttribute);
    $this->assertEquals(['bar' => 'foo'], $model->singleElementInArrayAttribute->toArray());
    $this->assertEquals(['bar' => 'foo'], $model->getAttribute('singleElementInArrayAttribute')->toArray());
});

test('using stringable object cast uses string representation', function () {
    $model = new InstrumentModelCastingStub;

    $this->assertEquals('int', $model->getCasts()['castStringableObject']);
});

test('mergeing stringable object cast u ses string representation', function () {
    $stringable = new StringableCastBuilder();
    $stringable->cast = 'test';

    $model = (new InstrumentModelCastingStub)->mergeCasts([
        'something' => $stringable,
    ]);

    $this->assertEquals('test', $model->getCasts()['something']);
});

test('using plain object as cast throws exception', function () {
    $model = new InstrumentModelCastingStub;


    $model->mergeCasts([
        'something' => (object) [],
    ]);
})->throws(InvalidArgumentException::class, 'The cast object for the something attribute must implement Stringable.');

test('unsaved model', function () {
    $user = new UnsavedModel;
    $user->name = null;

    $this->assertNull($user->name);
});

test('discard changes', function () {
    $user = new InstrumentModelStub([
        'name' => 'Taylor Otwell',
    ]);

    $this->assertNotEmpty($user->isDirty());
    $this->assertNull($user->getOriginal('name'));
    $this->assertSame('Taylor Otwell', $user->getAttribute('name'));

    $user->discardChanges();

    $this->assertEmpty($user->isDirty());
    $this->assertNull($user->getOriginal('name'));
    $this->assertNull($user->getAttribute('name'));
});

test('discard changes with casts', function () {
    $model = new InstrumentModelWithPrimitiveCasts();

    $model->address_line_one = '123 Main Street';

    $this->assertEquals('123 Main Street', $model->address->lineOne);
    $this->assertEquals('123 MAIN STREET', $model->address_in_caps);

    $model->discardChanges();

    $this->assertNull($model->address->lineOne);
    $this->assertNull($model->address_in_caps);
});

test('has attribute', function () {
    $user = new InstrumentModelStub([
        'name' => 'Mateus',
    ]);

    $this->assertTrue($user->hasAttribute('name'));
    $this->assertTrue($user->hasAttribute('password'));
    $this->assertTrue($user->hasAttribute('castedFloat'));
    $this->assertFalse($user->hasAttribute('nonexistent'));
    $this->assertFalse($user->hasAttribute('belongsToStub'));
});

test('model to json succeeds with prior errors', function () {
    $user = new InstrumentModelStub(['name' => 'Mateus']);

    // Simulate a JSON error
    json_decode('{');
    $this->assertTrue(json_last_error() !== JSON_ERROR_NONE);

    $this->assertSame('{"name":"Mateus"}', $user->toJson(JSON_THROW_ON_ERROR));
});

test('model to pretty json', function () {
    $user = new InstrumentModelStub(['name' => 'Mateus', 'active' => true, 'number' => '123']);
    $results = $user->toPrettyJson();
    $expected = $user->toJson(JSON_PRETTY_PRINT);

    $this->assertJsonStringEqualsJsonString($expected, $results);
    $this->assertSame($expected, $results);
    $this->assertStringContainsString("\n", $results);
    $this->assertStringContainsString('    ', $results);

    $results = $user->toPrettyJson(JSON_NUMERIC_CHECK);
    $this->assertStringContainsString("\n", $results);
    $this->assertStringContainsString('    ', $results);
    $this->assertStringContainsString('"number": 123', $results);
});

test('fillable with mutators', function () {
    $model = new InstrumentModelWithMutators;
    $model->fillable(['full_name', 'full_address']);
    $model->fill(['id' => 1, 'full_name' => 'John Doe', 'full_address' => '123 Main Street, Anytown']);

    $this->assertNull($model->id);
    $this->assertSame('John', $model->first_name);
    $this->assertSame('Doe', $model->last_name);
    $this->assertSame('123 Main Street', $model->address_line_one);
    $this->assertSame('Anytown', $model->address_line_two);
});

test('guarded with mutators', function () {
    $model = new InstrumentModelWithMutators;
    $model->guard(['id']);
    $model->fill(['id' => 1, 'full_name' => 'John Doe', 'full_address' => '123 Main Street, Anytown']);

    $this->assertNull($model->id);
    $this->assertSame('John', $model->first_name);
    $this->assertSame('Doe', $model->last_name);
    $this->assertSame('123 Main Street', $model->address_line_one);
    $this->assertSame('Anytown', $model->address_line_two);
});

test('collected by attribute', function () {
    $model = new InstrumentModelWithCollectedByAttribute;
    $collection = $model->newCollection([$model]);

    $this->assertInstanceOf(CustomInstrumentCollection::class, $collection);
});

test('use factory attribute', function () {
    $model = new InstrumentModelWithUseFactoryAttribute;
    $instance = InstrumentModelWithUseFactoryAttribute::factory()->make(['name' => 'test name']);
    $factory = InstrumentModelWithUseFactoryAttribute::factory();
    $this->assertInstanceOf(InstrumentModelWithUseFactoryAttribute::class, $instance);
    $this->assertInstanceOf(InstrumentModelWithUseFactoryAttributeFactory::class, $model::factory());
    $this->assertInstanceOf(InstrumentModelWithUseFactoryAttributeFactory::class, $model::newFactory());
    $this->assertEquals(InstrumentModelWithUseFactoryAttribute::class, $factory->modelName());
    $this->assertEquals('test name', $instance->name); // Small smoke test to ensure the factory is working
});

test('use custom builder with use instrument builder attribute', function () {
    $model = new InstrumentModelWithUseInstrumentBuilderAttributeStub();

    $query = $this->createMock(\Voyager\Database\Query\Builder::class);
    $instrumentBuilder = $model->newInstrumentBuilder($query);

    $this->assertInstanceOf(CustomBuilder::class, $instrumentBuilder);
});

test('default builder is used when use instrument builder attribute is not present', function () {
    $model = new InstrumentModelWithoutUseInstrumentBuilderAttributeStub();

    $query = $this->createMock(\Voyager\Database\Query\Builder::class);
    $instrumentBuilder = $model->newInstrumentBuilder($query);

    $this->assertNotInstanceOf(CustomBuilder::class, $instrumentBuilder);
});


class CustomBuilder extends Builder
{
}

#[\Voyager\Database\Instrument\Attributes\UseInstrumentBuilder(CustomBuilder::class)]
class InstrumentModelWithUseInstrumentBuilderAttributeStub extends Model
{
}

class InstrumentModelWithoutUseInstrumentBuilderAttributeStub extends Model
{
}

class InstrumentTestObserverStub
{
    public function creating()
    {
        //
    }

    public function saved()
    {
        //
    }
}

class InstrumentTestAnotherObserverStub
{
    public function creating()
    {
        //
    }

    public function saved()
    {
        //
    }
}

class InstrumentTestThirdObserverStub
{
    public function creating()
    {
        //
    }

    public function saved()
    {
        //
    }
}

class InstrumentModelStub extends Model
{
    public $connection;
    public $scopesCalled = [];
    protected $table = 'stub';
    protected $guarded = [];
    protected $casts = ['castedFloat' => 'float'];

    public function getListItemsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setListItemsAttribute($value)
    {
        $this->attributes['list_items'] = json_encode($value);
    }

    public function getPasswordAttribute()
    {
        return '******';
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password_hash'] = sha1($value);
    }

    public function publicIncrement($column, $amount = 1, $extra = [])
    {
        return $this->increment($column, $amount, $extra);
    }

    public function publicIncrementQuietly($column, $amount = 1, $extra = [])
    {
        return $this->incrementQuietly($column, $amount, $extra);
    }

    public function publicDecrementQuietly($column, $amount = 1, $extra = [])
    {
        return $this->decrementQuietly($column, $amount, $extra);
    }

    public function belongsToStub()
    {
        return $this->belongsTo(InstrumentModelSaveStub::class);
    }

    public function morphToStub()
    {
        return $this->morphTo();
    }

    public function morphToStubWithKeys()
    {
        return $this->morphTo(null, 'type', 'id');
    }

    public function morphToStubWithName()
    {
        return $this->morphTo('someName');
    }

    public function morphToStubWithNameAndKeys()
    {
        return $this->morphTo('someName', 'type', 'id');
    }

    public function belongsToExplicitKeyStub()
    {
        return $this->belongsTo(InstrumentModelSaveStub::class, 'foo');
    }

    public function incorrectRelationStub()
    {
        return 'foo';
    }

    public function getDates()
    {
        return [];
    }

    public function getAppendableAttribute()
    {
        return 'appended';
    }

    public function scopePublished(Builder $builder)
    {
        $this->scopesCalled[] = 'published';
    }

    public function scopeCategory(Builder $builder, $category)
    {
        $this->scopesCalled['category'] = $category;
    }

    public function scopeFramework(Builder $builder, $framework, $version)
    {
        $this->scopesCalled['framework'] = [$framework, $version];
    }

    public function scopeDate(Builder $builder, Carbon $date)
    {
        $this->scopesCalled['date'] = $date;
    }
}

trait FooBarTrait
{
    public $fooBarIsInitialized = false;

    public function initializeFooBarTrait()
    {
        $this->fooBarIsInitialized = true;
    }
}

class InstrumentModelStubWithTrait extends InstrumentModelStub
{
    use FooBarTrait;
}

class InstrumentModelCamelStub extends InstrumentModelStub
{
    public static $snakeAttributes = false;
}

class InstrumentDateModelStub extends InstrumentModelStub
{
    public function getDates()
    {
        return ['created_at', 'updated_at'];
    }
}

class InstrumentModelSaveStub extends Model
{
    protected $table = 'save_stub';
    protected $guarded = [];

    public function save(array $options = [])
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        $_SERVER['__instrument.saved'] = true;

        $this->fireModelEvent('saved', false);
    }

    public function setIncrementing($value)
    {
        $this->incrementing = $value;
    }

    public function getConnection()
    {
        $mock = m::mock(Connection::class);
        $mock->shouldReceive('getQueryGrammar')->andReturn($grammar = m::mock(Grammar::class));
        $grammar->shouldReceive('getBitwiseOperators')->andReturn([]);
        $grammar->shouldReceive('isExpression')->andReturnFalse();
        $mock->shouldReceive('getPostProcessor')->andReturn($processor = m::mock(Processor::class));
        $mock->shouldReceive('getName')->andReturn('name');
        $mock->shouldReceive('query')->andReturnUsing(function () use ($mock, $grammar, $processor) {
            return new BaseBuilder($mock, $grammar, $processor);
        });

        return $mock;
    }
}

class InstrumentKeyTypeModelStub extends InstrumentModelStub
{
    protected $keyType = 'string';
}

class InstrumentModelFindWithWritePdoStub extends Model
{
    public function newQuery()
    {
        $mock = m::mock(Builder::class);
        $mock->shouldReceive('useWritePdo')->once()->andReturnSelf();
        $mock->shouldReceive('find')->once()->with(1)->andReturn('foo');

        return $mock;
    }
}

class InstrumentModelDestroyStub extends Model
{
    protected $fillable = [
        'id',
    ];

    public function newQuery()
    {
        $mock = m::mock(Builder::class);
        $mock->shouldReceive('whereIn')->once()->with('id', [1, 2, 3])->andReturn($mock);
        $mock->shouldReceive('get')->once()->andReturn([$model = m::mock(stdClass::class)]);
        $model->shouldReceive('delete')->once();

        return $mock;
    }
}

class InstrumentModelEmptyDestroyStub extends Model
{
    public function newQuery()
    {
        $mock = m::mock(Builder::class);
        $mock->shouldReceive('whereIn')->never();

        return $mock;
    }
}

class InstrumentModelWithStub extends Model
{
    public function newQuery()
    {
        $mock = m::mock(Builder::class);
        $mock->shouldReceive('with')->once()->with(['foo', 'bar'])->andReturn('foo');

        return $mock;
    }
}

class InstrumentModelWithWhereHasStub extends Model
{
    public function foo()
    {
        return $this->hasMany(InstrumentModelStub::class);
    }
}

class InstrumentModelWithoutRelationStub extends Model
{
    public $with = ['foo'];

    protected $guarded = [];

    public function getEagerLoads()
    {
        return $this->eagerLoads;
    }
}

class InstrumentModelWithoutTableStub extends Model
{
    //
}

class InstrumentModelBootingTestStub extends Model
{
    public static function unboot()
    {
        unset(static::$booted[static::class]);
        unset(static::$bootedCallbacks[static::class]);
    }

    public static function isBooted()
    {
        return array_key_exists(static::class, static::$booted);
    }
}

class InstrumentModelAppendsStub extends Model
{
    protected $appends = ['is_admin', 'camelCased', 'StudlyCased'];

    public function getIsAdminAttribute()
    {
        return 'admin';
    }

    public function getCamelCasedAttribute()
    {
        return 'camelCased';
    }

    public function getStudlyCasedAttribute()
    {
        return 'StudlyCased';
    }
}

class InstrumentModelGetMutatorsStub extends Model
{
    public static function resetMutatorCache()
    {
        static::$mutatorCache = [];
    }

    public function getFirstNameAttribute()
    {
        //
    }

    public function getMiddleNameAttribute()
    {
        //
    }

    public function getLastNameAttribute()
    {
        //
    }

    public function doNotgetFirstInvalidAttribute()
    {
        //
    }

    public function doNotGetSecondInvalidAttribute()
    {
        //
    }

    public function doNotgetThirdInvalidAttributeEither()
    {
        //
    }

    public function doNotGetFourthInvalidAttributeEither()
    {
        //
    }
}

class InstrumentModelCastingStub extends Model
{
    protected $casts = [
        'floatAttribute' => 'float',
        'boolAttribute' => 'bool',
        'objectAttribute' => 'object',
        'jsonAttribute' => 'json',
        'jsonAttributeWithUnicode' => 'json:unicode',
        'dateAttribute' => 'date',
        'timestampAttribute' => 'timestamp',
        'ascollectionAttribute' => AsCollection::class,
        'asCustomCollectionAsArrayAttribute' => [AsCollection::class, CustomCollection::class],
        'asEncryptedCollectionAttribute' => AsEncryptedCollection::class,
        'asEnumCollectionAttribute' => AsEnumCollection::class.':'.StringStatus::class,
        'asEnumArrayObjectAttribute' => AsEnumArrayObject::class.':'.StringStatus::class,
        'duplicatedAttribute' => 'string',
    ];

    protected function casts(): array
    {
        return [
            'intAttribute' => 'int',
            'stringAttribute' => 'string',
            'booleanAttribute' => 'boolean',
            'arrayAttribute' => 'array',
            'collectionAttribute' => 'collection',
            'datetimeAttribute' => 'datetime',
            'asarrayobjectAttribute' => AsArrayObject::class,
            'asStringableAttribute' => AsStringable::class,
            'asHtmlStringAttribute' => AsHtmlString::class,
            'asUriAttribute' => AsUri::class,
            'asFluentAttribute' => AsFluent::class,
            'asCustomCollectionAttribute' => AsCollection::using(CustomCollection::class),
            'asEncryptedArrayObjectAttribute' => AsEncryptedArrayObject::class,
            'asEncryptedCustomCollectionAttribute' => AsEncryptedCollection::using(CustomCollection::class),
            'asEncryptedCustomCollectionAsArrayAttribute' => [AsEncryptedCollection::class, CustomCollection::class],
            'asCustomEnumCollectionAttribute' => AsEnumCollection::of(StringStatus::class),
            'asCustomEnumArrayObjectAttribute' => AsEnumArrayObject::of(StringStatus::class),
            'singleElementInArrayAttribute' => [AsCollection::class],
            'duplicatedAttribute' => 'int',
            'asToObjectCast' => TestCast::class,
            'castStringableObject' => new StringableCastBuilder(),
        ];
    }

    public function jsonAttributeValue()
    {
        return $this->attributes['jsonAttribute'];
    }

    public function jsonAttributeWithUnicodeValue()
    {
        return $this->attributes['jsonAttributeWithUnicode'];
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}

class InstrumentModelEnumCastingStub extends Model
{
    protected $casts = ['enumAttribute' => StringStatus::class];
}

class InstrumentModelDynamicHiddenStub extends Model
{
    protected $table = 'stub';
    protected $guarded = [];

    public function getHidden()
    {
        return ['age', 'id'];
    }
}

class InstrumentModelVisibleStub extends Model
{
    protected $table = 'stub';
    protected $visible = ['foo'];
}

class InstrumentModelHiddenStub extends Model
{
    protected $table = 'stub';
    protected $hidden = ['foo'];
}

class InstrumentModelDynamicVisibleStub extends Model
{
    protected $table = 'stub';
    protected $guarded = [];

    public function getVisible()
    {
        return ['name', 'id'];
    }
}

class InstrumentModelNonIncrementingStub extends Model
{
    protected $table = 'stub';
    protected $guarded = [];
    public $incrementing = false;
}

class InstrumentNoConnectionModelStub extends InstrumentModelStub
{
    //
}

class InstrumentDifferentConnectionModelStub extends InstrumentModelStub
{
    public $connection = 'different_connection';
}

class InstrumentPrimaryUuidModelStub extends InstrumentModelStub
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    public function getKeyName()
    {
        return 'uuid';
    }
}

class InstrumentNonPrimaryUuidModelStub extends InstrumentModelStub
{
    use HasUuids;

    public function getKeyName()
    {
        return 'id';
    }

    public function uniqueIds()
    {
        return ['uuid'];
    }
}

class InstrumentPrimaryUlidModelStub extends InstrumentModelStub
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    public function getKeyName()
    {
        return 'ulid';
    }
}

class InstrumentNonPrimaryUlidModelStub extends InstrumentModelStub
{
    use HasUlids;

    public function getKeyName()
    {
        return 'id';
    }

    public function uniqueIds()
    {
        return ['ulid'];
    }
}

#[ObservedBy(InstrumentTestObserverStub::class)]
class InstrumentModelWithObserveAttributeStub extends InstrumentModelStub
{
    //
}

#[ObservedBy([InstrumentTestObserverStub::class])]
class InstrumentModelWithObserveAttributeUsingArrayStub extends InstrumentModelStub
{
    //
}

#[ObservedBy([InstrumentTestObserverStub::class])]
class InstrumentModelWithObserveAttributeGrandparentStub extends InstrumentModelStub
{
    //
}

#[ObservedBy([InstrumentTestAnotherObserverStub::class])]
class InstrumentModelWithObserveAttributeParentStub extends InstrumentModelWithObserveAttributeGrandparentStub
{
    //
}

#[ObservedBy([InstrumentTestThirdObserverStub::class])]
class InstrumentModelWithObserveAttributeGrandchildStub extends InstrumentModelWithObserveAttributeParentStub
{
    //
}

class InstrumentModelSavingEventStub
{
    //
}

class InstrumentModelEventObjectStub extends Model
{
    protected $dispatchesEvents = [
        'saving' => InstrumentModelSavingEventStub::class,
    ];
}

class InstrumentModelWithoutTimestamps extends Model
{
    protected $table = 'stub';
    public $timestamps = false;
}

class InstrumentModelWithUpdatedAtNull extends Model
{
    protected $table = 'stub';
    const UPDATED_AT = null;
}

class UnsavedModel extends Model
{
    protected $casts = ['name' => Uppercase::class];
}

class Uppercase implements CastsInboundAttributes
{
    public function set($model, string $key, $value, array $attributes)
    {
        return is_string($value) ? strtoupper($value) : $value;
    }
}

class CustomCollection extends BaseCollection
{
    //
}

class InstrumentModelWithPrimitiveCasts extends Model
{
    public $fillable = ['id'];

    public $casts = [
        'backed_enum' => CastableBackedEnum::class,
        'address' => Address::class,
    ];

    public $attributes = [
        'address_line_one' => null,
        'address_line_two' => null,
    ];

    public static function makePrimitiveCastsArray(): array
    {
        $toReturn = [];

        foreach (static::$primitiveCastTypes as $index => $primitiveCastType) {
            $toReturn['primitive_cast_'.$index] = $primitiveCastType;
        }

        return $toReturn;
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->mergeCasts(self::makePrimitiveCastsArray());
    }

    public function getThisIsFineAttribute($value)
    {
        return 'ok';
    }

    public function thisIsAlsoFine(): Attribute
    {
        return Attribute::get(fn () => 'ok');
    }

    public function addressInCaps(): Attribute
    {
        return Attribute::get(
            function () {
                $value = $this->getAttributes()['address_line_one'] ?? null;

                return is_string($value) ? strtoupper($value) : $value;
            }
        )->shouldCache();
    }
}

enum CastableBackedEnum: string
{
    case Value1 = 'value1';
}

class Address implements Castable
{
    public function __construct(
        public ?string $lineOne = null,
        public ?string $lineTwo = null
    ) {
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): Address
            {
                return new Address(
                    $attributes['address_line_one'],
                    $attributes['address_line_two']
                );
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): array
            {
                return [
                    'address_line_one' => $value->lineOne ?? null,
                    'address_line_two' => $value->lineTwo ?? null,
                ];
            }
        };
    }
}

class InstrumentModelWithRecursiveRelationshipsStub extends Model
{
    public $fillable = ['id', 'parent_id'];

    protected static \WeakMap $recursionDetectionCache;

    public function getQueueableRelations()
    {
        try {
            $this->stepIn();

            return parent::getQueueableRelations();
        } finally {
            $this->stepOut();
        }
    }

    public function push()
    {
        try {
            $this->stepIn();

            return parent::push();
        } finally {
            $this->stepOut();
        }
    }

    public function save(array $options = [])
    {
        return true;
    }

    public function relationsToArray()
    {
        try {
            $this->stepIn();

            return parent::relationsToArray();
        } finally {
            $this->stepOut();
        }
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function self(): BelongsTo
    {
        return $this->belongsTo(static::class, 'id');
    }

    protected static function getRecursionDetectionCache()
    {
        return static::$recursionDetectionCache ??= new \WeakMap;
    }

    protected function getRecursionDepth(): int
    {
        $cache = static::getRecursionDetectionCache();

        return $cache->offsetExists($this) ? $cache->offsetGet($this) : 0;
    }

    protected function stepIn(): void
    {
        $depth = $this->getRecursionDepth();

        if ($depth > 1) {
            throw new \RuntimeException('Recursion detected');
        }
        static::getRecursionDetectionCache()->offsetSet($this, $depth + 1);
    }

    protected function stepOut(): void
    {
        $cache = static::getRecursionDetectionCache();
        if ($depth = $this->getRecursionDepth()) {
            $cache->offsetSet($this, $depth - 1);
        } else {
            $cache->offsetUnset($this);
        }
    }
}

class InstrumentModelWithMutators extends Model
{
    public $attributes = [
        'first_name' => null,
        'last_name' => null,
        'address_line_one' => null,
        'address_line_two' => null,
    ];

    protected function fullName(): Attribute
    {
        return Attribute::make(
            set: function (string $fullName) {
                [$firstName, $lastName] = explode(' ', $fullName);

                return [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ];
            }
        );
    }

    public function setFullAddressAttribute($fullAddress)
    {
        [$addressLineOne, $addressLineTwo] = explode(', ', $fullAddress);

        $this->attributes['address_line_one'] = $addressLineOne;
        $this->attributes['address_line_two'] = $addressLineTwo;
    }
}

#[CollectedBy(CustomInstrumentCollection::class)]
class InstrumentModelWithCollectedByAttribute extends Model
{
}

class CustomInstrumentCollection extends Collection
{
}

class InstrumentModelWithUseFactoryAttributeFactory extends Factory
{
    public function definition()
    {
        return [];
    }
}

#[UseFactory(InstrumentModelWithUseFactoryAttributeFactory::class)]
class InstrumentModelWithUseFactoryAttribute extends Model
{
    use HasFactory;
}

trait InstrumentTraitBootingCallbackTestStub
{
    public static function bootInstrumentTraitBootingCallbackTestStub()
    {
        static::whenBooted(fn () => static::$bootHasFinished = true);
    }
}

class InstrumentModelBootingCallbackTestStub extends Model
{
    use InstrumentTraitBootingCallbackTestStub;

    public static bool $bootHasFinished = false;

    public static function unboot()
    {
        unset(static::$booted[static::class]);
        unset(static::$bootedCallbacks[static::class]);
        static::$bootHasFinished = false;
    }
}

class InstrumentChildModelBootingCallbackTestStub extends InstrumentModelBootingCallbackTestStub
{
    public static bool $bootHasFinished = false;
}

class StringableCastBuilder implements NativeStringable
{
    public $cast = 'int';

    public function __toString()
    {
        return $this->cast;
    }
}

enum ConnectionName
{
    case Foo;
    case Bar;
}

enum ConnectionNameBacked: string
{
    case Foo = 'Foo';
    case Bar = 'Bar';
}
