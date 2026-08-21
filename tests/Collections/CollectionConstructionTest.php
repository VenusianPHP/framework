<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (empty/
 * construction/skip/getArrayableItems/serialisation/array access/forget/
 * countable/countBy/add/containsOneItem/containsManyItems/iterable/filter).
 */

use Tests\Collections\Fixtures\TestAccessorEloquentTestStub;
use Tests\Collections\StaffEnum;
use Tests\NutsAndBolts\Fixtures\TestArrayableObject;
use Tests\NutsAndBolts\Fixtures\TestJsonableObject;
use Tests\NutsAndBolts\Fixtures\TestJsonSerializeObject;
use Tests\NutsAndBolts\Fixtures\TestJsonSerializeWithScalarValueObject;
use Tests\NutsAndBolts\Fixtures\TestTraversableAndJsonSerializableObject;
use Tests\NutsAndBolts\TestBackedEnum;
use Tests\NutsAndBolts\TestStringBackedEnum;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\LazyCollection;

test('emptyCollectionIsEmpty', function (string $collection) {
    $c = new $collection;

    $this->assertTrue($c->isEmpty());
})->with('collections');

test('emptyCollectionIsNotEmpty', function (string $collection) {
    $c = new $collection(['foo', 'bar']);

    $this->assertFalse($c->isEmpty());
    $this->assertTrue($c->isNotEmpty());
})->with('collections');

test('collectionIsConstructed', function (string $collection) {
    $data = new $collection('foo');
    $this->assertSame(['foo'], $data->all());

    $data = new $collection(2);
    $this->assertSame([2], $data->all());

    $data = new $collection(false);
    $this->assertSame([false], $data->all());

    $data = new $collection(null);
    $this->assertEmpty($data->all());

    $data = new $collection;
    $this->assertEmpty($data->all());
})->with('collections');

test('skipMethod', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6]);

    // Total items to skip is smaller than collection length
    $this->assertSame([5, 6], $data->skip(4)->values()->all());

    // Total items to skip is more than collection length
    $this->assertSame([], $data->skip(10)->values()->all());
})->with('collections');

test('skipUntil', function (string $collection) {
    $data = new $collection([1, 1, 2, 2, 3, 3, 4, 4]);

    // Item at the beginning of the collection
    $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4], $data->skipUntil(1)->values()->all());

    // Item at the middle of the collection
    $this->assertSame([3, 3, 4, 4], $data->skipUntil(3)->values()->all());

    // Item not in the collection
    $this->assertSame([], $data->skipUntil(5)->values()->all());

    // Item at the beginning of the collection
    $data = $data->skipUntil(function ($value, $key) {
        return $value <= 1;
    })->values();

    $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4], $data->all());

    // Item at the middle of the collection
    $data = $data->skipUntil(function ($value, $key) {
        return $value >= 3;
    })->values();

    $this->assertSame([3, 3, 4, 4], $data->all());

    // Item not in the collection
    $data = $data->skipUntil(function ($value, $key) {
        return $value >= 5;
    })->values();

    $this->assertSame([], $data->all());
})->with('collections');

test('skipWhile', function (string $collection) {
    $data = new $collection([1, 1, 2, 2, 3, 3, 4, 4]);

    // Item at the beginning of the collection
    $this->assertSame([2, 2, 3, 3, 4, 4], $data->skipWhile(1)->values()->all());

    // Item not in the collection
    $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4], $data->skipWhile(5)->values()->all());

    // Item in the collection but not at the beginning
    $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4], $data->skipWhile(2)->values()->all());

    // Item not in the collection
    $data = $data->skipWhile(function ($value, $key) {
        return $value >= 5;
    })->values();

    $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4], $data->all());

    // Item in the collection but not at the beginning
    $data = $data->skipWhile(function ($value, $key) {
        return $value >= 2;
    })->values();

    $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4], $data->all());

    // Item at the beginning of the collection
    $data = $data->skipWhile(function ($value, $key) {
        return $value < 3;
    })->values();

    $this->assertSame([3, 3, 4, 4], $data->all());
})->with('collections');

test('getArrayableItems', function (string $collection) {
    $data = new $collection;

    $class = new ReflectionClass($collection);
    $method = $class->getMethod('getArrayableItems');

    $items = new TestArrayableObject;
    $array = $method->invokeArgs($data, [$items]);
    $this->assertSame(['foo' => 'bar'], $array);

    $items = new TestJsonableObject;
    $array = $method->invokeArgs($data, [$items]);
    $this->assertSame(['foo' => 'bar'], $array);

    $items = new TestJsonSerializeObject;
    $array = $method->invokeArgs($data, [$items]);
    $this->assertSame(['foo' => 'bar'], $array);

    $items = new TestJsonSerializeWithScalarValueObject;
    $array = $method->invokeArgs($data, [$items]);
    $this->assertSame(['foo'], $array);

    $subject = [new stdClass, new stdClass];
    $items = new TestTraversableAndJsonSerializableObject($subject);
    $array = $method->invokeArgs($data, [$items]);
    $this->assertSame($subject, $array);

    $items = new $collection(['foo' => 'bar']);
    $array = $method->invokeArgs($data, [$items]);
    $this->assertSame(['foo' => 'bar'], $array);

    $items = ['foo' => 'bar'];
    $array = $method->invokeArgs($data, [$items]);
    $this->assertSame(['foo' => 'bar'], $array);
})->with('collections');

test('toArrayCallsToArrayOnEachItemInCollection', function (string $collection) {
    $item1 = Mockery::mock(Arrayable::class);
    $item1->shouldReceive('toArray')->once()->andReturn('foo.array');
    $item2 = Mockery::mock(Arrayable::class);
    $item2->shouldReceive('toArray')->once()->andReturn('bar.array');
    $c = new $collection([$item1, $item2]);
    $results = $c->toArray();

    $this->assertEquals(['foo.array', 'bar.array'], $results);
})->with('collections');

test('lazyReturnsLazyCollection', function () {
    $data = new Collection([1, 2, 3, 4, 5]);

    $lazy = $data->lazy();

    $data->add(6);

    $this->assertInstanceOf(LazyCollection::class, $lazy);
    $this->assertSame([1, 2, 3, 4, 5], $lazy->all());
});

test('jsonSerializeCallsToArrayOrJsonSerializeOnEachItemInCollection', function (string $collection) {
    $item1 = Mockery::mock(JsonSerializable::class);
    $item1->shouldReceive('jsonSerialize')->once()->andReturn('foo.json');
    $item2 = Mockery::mock(Arrayable::class);
    $item2->shouldReceive('toArray')->once()->andReturn('bar.array');
    $c = new $collection([$item1, $item2]);
    $results = $c->jsonSerialize();

    $this->assertEquals(['foo.json', 'bar.array'], $results);
})->with('collections');

test('toJsonEncodesTheJsonSerializeResult', function (string $collection) {
    $c = $this->getMockBuilder($collection)->onlyMethods(['jsonSerialize'])->getMock();
    $c->expects($this->once())->method('jsonSerialize')->willReturn(['foo']);
    $results = $c->toJson();
    $this->assertJsonStringEqualsJsonString(json_encode(['foo']), $results);
})->with('collections');

test('toPrettyJsonEncodesTheJsonSerializeResult', function (string $collection) {
    $c = $this->getMockBuilder($collection)->onlyMethods(['jsonSerialize'])->getMock();
    $c->expects($this->once())->method('jsonSerialize')->willReturn(['foo' => 'bar', 'baz' => 'qux']);
    $results = $c->toPrettyJson();
    $expected = json_encode(['foo' => 'bar', 'baz' => 'qux'], JSON_PRETTY_PRINT);
    $this->assertJsonStringEqualsJsonString($expected, $results);
    $this->assertSame($expected, $results);
    $this->assertStringContainsString("\n", $results);
    $this->assertStringContainsString('    ', $results);
})->with('collections');

test('castingToStringJsonEncodesTheToArrayResult', function (string $collection) {
    $c = $this->getMockBuilder($collection)->onlyMethods(['jsonSerialize'])->getMock();
    $c->expects($this->once())->method('jsonSerialize')->willReturn(['foo']);

    $this->assertJsonStringEqualsJsonString(json_encode(['foo']), (string) $c);
})->with('collections');

test('offsetAccess', function () {
    $c = new Collection(['name' => 'taylor']);
    $this->assertSame('taylor', $c['name']);
    $c['name'] = 'dayle';
    $this->assertSame('dayle', $c['name']);
    $this->assertTrue(isset($c['name']));
    unset($c['name']);
    $this->assertFalse(isset($c['name']));
    $c[] = 'jason';
    $this->assertSame('jason', $c[0]);
});

test('arrayAccessOffsetExists', function () {
    $c = new Collection(['foo', 'bar', null]);
    $this->assertTrue($c->offsetExists(0));
    $this->assertTrue($c->offsetExists(1));
    $this->assertFalse($c->offsetExists(2));
});

test('behavesLikeAnArrayWithArrayAccess', function () {
    // indexed array
    $input = ['foo', null];
    $c = new Collection($input);
    $this->assertEquals(isset($input[0]), isset($c[0])); // existing value
    $this->assertEquals(isset($input[1]), isset($c[1])); // existing but null value
    $this->assertEquals(isset($input[1000]), isset($c[1000])); // non-existing value
    $this->assertEquals($input[0], $c[0]);
    $this->assertEquals($input[1], $c[1]);

    // associative array
    $input = ['k1' => 'foo', 'k2' => null];
    $c = new Collection($input);
    $this->assertEquals(isset($input['k1']), isset($c['k1'])); // existing value
    $this->assertEquals(isset($input['k2']), isset($c['k2'])); // existing but null value
    $this->assertEquals(isset($input['k3']), isset($c['k3'])); // non-existing value
    $this->assertEquals($input['k1'], $c['k1']);
    $this->assertEquals($input['k2'], $c['k2']);
});

test('arrayAccessOffsetGet', function () {
    $c = new Collection(['foo', 'bar']);
    $this->assertSame('foo', $c->offsetGet(0));
    $this->assertSame('bar', $c->offsetGet(1));
});

test('arrayAccessOffsetSet', function () {
    $c = new Collection(['foo', 'foo']);

    $c->offsetSet(1, 'bar');
    $this->assertSame('bar', $c[1]);

    $c->offsetSet(null, 'qux');
    $this->assertSame('qux', $c[2]);
});

test('arrayAccessOffsetUnset', function () {
    $c = new Collection(['foo', 'bar']);

    $c->offsetUnset(1);
    $this->assertFalse(isset($c[1]));
});

test('forgetSingleKey', function () {
    $c = new Collection(['foo', 'bar']);
    $c = $c->forget(0)->all();
    $this->assertFalse(isset($c['foo']));
    $this->assertFalse(isset($c[0]));
    $this->assertTrue(isset($c[1]));

    $c = new Collection(['foo' => 'bar', 'baz' => 'qux']);
    $c = $c->forget('foo')->all();
    $this->assertFalse(isset($c['foo']));
    $this->assertTrue(isset($c['baz']));
});

test('forgetArrayOfKeys', function () {
    $c = new Collection(['foo', 'bar', 'baz']);
    $c = $c->forget([0, 2])->all();
    $this->assertFalse(isset($c[0]));
    $this->assertFalse(isset($c[2]));
    $this->assertTrue(isset($c[1]));

    $c = new Collection(['name' => 'taylor', 'foo' => 'bar', 'baz' => 'qux']);
    $c = $c->forget(['foo', 'baz'])->all();
    $this->assertFalse(isset($c['foo']));
    $this->assertFalse(isset($c['baz']));
    $this->assertTrue(isset($c['name']));
});

test('forgetCollectionOfKeys', function () {
    $c = new Collection(['foo', 'bar', 'baz']);
    $c = $c->forget(collect([0, 2]))->all();
    $this->assertFalse(isset($c[0]));
    $this->assertFalse(isset($c[2]));
    $this->assertTrue(isset($c[1]));

    $c = new Collection(['name' => 'taylor', 'foo' => 'bar', 'baz' => 'qux']);
    $c = $c->forget(collect(['foo', 'baz']))->all();
    $this->assertFalse(isset($c['foo']));
    $this->assertFalse(isset($c['baz']));
    $this->assertTrue(isset($c['name']));
});

test('countable', function (string $collection) {
    $c = new $collection(['foo', 'bar']);
    $this->assertCount(2, $c);
})->with('collections');

test('countByStandalone', function (string $collection) {
    $c = new $collection(['foo', 'foo', 'foo', 'bar', 'bar', 'foobar']);
    $this->assertEquals(['foo' => 3, 'bar' => 2, 'foobar' => 1], $c->countBy()->all());

    $c = new $collection([true, true, false, false, false]);
    $this->assertEquals([true => 2, false => 3], $c->countBy()->all());

    $c = new $collection([1, 5, 1, 5, 5, 1]);
    $this->assertEquals([1 => 3, 5 => 3], $c->countBy()->all());

    $c = new $collection([StaffEnum::James, StaffEnum::Joe, StaffEnum::Taylor]);
    $this->assertEquals(['James' => 1, 'Joe' => 1, 'Taylor' => 1], $c->countBy()->all());
})->with('collections');

test('countByWithKey', function (string $collection) {
    $c = new $collection([
        ['key' => 'a'], ['key' => 'a'], ['key' => 'a'], ['key' => 'a'],
        ['key' => 'b'], ['key' => 'b'], ['key' => 'b'],
    ]);
    $this->assertEquals(['a' => 4, 'b' => 3], $c->countBy('key')->all());

    $c = new $collection([
        ['key' => TestBackedEnum::A],
        ['key' => TestBackedEnum::B], ['key' => TestBackedEnum::B],
    ]);
    $this->assertEquals([1 => 1, 2 => 2], $c->countBy('key')->all());
})->with('collections');

test('countableByWithCallback', function (string $collection) {
    $c = new $collection(['alice', 'aaron', 'bob', 'carla']);
    $this->assertEquals(['a' => 2, 'b' => 1, 'c' => 1], $c->countBy(function ($name) {
        return substr($name, 0, 1);
    })->all());

    $c = new $collection([1, 2, 3, 4, 5]);
    $this->assertEquals([true => 2, false => 3], $c->countBy(function ($i) {
        return $i % 2 === 0;
    })->all());

    $c = new $collection(['A', 'A', 'B', 'A']);
    $this->assertEquals(['A' => 3, 'B' => 1], $c->countBy(static fn ($i) => TestStringBackedEnum::from($i))->all());
})->with('collections');

test('add', function () {
    $c = new Collection([]);
    $this->assertEquals([1], $c->add(1)->values()->all());
    $this->assertEquals([1, 2], $c->add(2)->values()->all());
    $this->assertEquals([1, 2, ''], $c->add('')->values()->all());
    $this->assertEquals([1, 2, '', null], $c->add(null)->values()->all());
    $this->assertEquals([1, 2, '', null, false], $c->add(false)->values()->all());
    $this->assertEquals([1, 2, '', null, false, []], $c->add([])->values()->all());
    $this->assertEquals([1, 2, '', null, false, [], 'name'], $c->add('name')->values()->all());
});

test('containsOneItem', function (string $collection) {
    $this->assertFalse((new $collection([]))->containsOneItem());
    $this->assertTrue((new $collection([1]))->containsOneItem());
    $this->assertFalse((new $collection([1, 2]))->containsOneItem());

    $this->assertFalse(collect([1, 2, 2])->containsOneItem(fn ($number) => $number === 2));
    $this->assertTrue(collect(['ant', 'bear', 'cat'])->containsOneItem(fn ($word) => strlen($word) === 4));
    $this->assertFalse(collect(['ant', 'bear', 'cat'])->containsOneItem(fn ($word) => strlen($word) > 4));
})->with('collections');

test('containsManyItems', function (string $collection) {
    $this->assertFalse((new $collection([]))->containsManyItems());
    $this->assertFalse((new $collection([1]))->containsManyItems());
    $this->assertTrue((new $collection([1, 2]))->containsManyItems());
    $this->assertTrue((new $collection([1, 2, 3]))->containsManyItems());

    $this->assertTrue(collect([1, 2, 2])->containsManyItems(fn ($number) => $number === 2));
    $this->assertFalse(collect(['ant', 'bear', 'cat'])->containsManyItems(fn ($word) => strlen($word) === 4));
    $this->assertFalse(collect(['ant', 'bear', 'cat'])->containsManyItems(fn ($word) => strlen($word) > 4));
    $this->assertTrue(collect(['ant', 'bear', 'cat'])->containsManyItems(fn ($word) => strlen($word) === 3));
})->with('collections');

test('iterable', function () {
    $c = new Collection(['foo']);
    $this->assertInstanceOf(ArrayIterator::class, $c->getIterator());
    $this->assertEquals(['foo'], $c->getIterator()->getArrayCopy());
});

test('cachingIterator', function (string $collection) {
    $c = new $collection(['foo']);
    $this->assertInstanceOf(CachingIterator::class, $c->getCachingIterator());
})->with('collections');

test('filter', function (string $collection) {
    $c = new $collection([['id' => 1, 'name' => 'Hello'], ['id' => 2, 'name' => 'World']]);
    $this->assertEquals([1 => ['id' => 2, 'name' => 'World']], $c->filter(function ($item) {
        return $item['id'] == 2;
    })->all());

    $c = new $collection(['', 'Hello', '', 'World']);
    $this->assertEquals(['Hello', 'World'], $c->filter()->values()->toArray());

    $c = new $collection(['id' => 1, 'first' => 'Hello', 'second' => 'World']);
    $this->assertEquals(['first' => 'Hello', 'second' => 'World'], $c->filter(function ($item, $key) {
        return $key != 'id';
    })->all());
})->with('collections');

test('getPluckValueWithAccessors', function () {
    $model = new TestAccessorEloquentTestStub(['some' => 'foo']);
    $modelTwo = new TestAccessorEloquentTestStub(['some' => 'bar']);
    $data = new Collection([$model, $modelTwo]);

    $this->assertSame(['foo', 'bar'], $data->pluck('some')->all());
});
