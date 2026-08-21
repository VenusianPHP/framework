<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (macroable,
 * proxy, make, wrap, unwrap, empty, times, range, fromJson, construction,
 * splice).
 */

use Tests\Collections\Fixtures\TestCollectionSubclass;
use Tests\NutsAndBolts\Fixtures\TestArrayableObject;
use Tests\NutsAndBolts\Fixtures\TestJsonableObject;
use Tests\NutsAndBolts\Fixtures\TestJsonSerializeObject;
use Voyager\NutsAndBolts\Collection;

test('macroable', function (string $collection) {
    // Foo() macro : unique values starting with A
    $collection::macro('foo', function () {
        return $this->filter(function ($item) {
            return str_starts_with($item, 'a');
        })
            ->unique()
            ->values();
    });

    $c = new $collection(['a', 'a', 'aa', 'aaa', 'bar']);

    $this->assertSame(['a', 'aa', 'aaa'], $c->foo()->all());
})->with('collections');

test('canAddMethodsToProxy', function (string $collection) {
    $collection::macro('adults', function ($callback) {
        return $this->filter(function ($item) use ($callback) {
            return $callback($item) >= 18;
        });
    });

    $collection::proxy('adults');

    $c = new $collection([['age' => 3], ['age' => 12], ['age' => 18], ['age' => 56]]);

    $this->assertSame([['age' => 18], ['age' => 56]], $c->adults->age->values()->all());
})->with('collections');

test('makeMethod', function (string $collection) {
    $data = $collection::make('foo');
    $this->assertEquals(['foo'], $data->all());
})->with('collections');

test('makeMethodFromNull', function (string $collection) {
    $data = $collection::make(null);
    $this->assertEquals([], $data->all());

    $data = $collection::make();
    $this->assertEquals([], $data->all());
})->with('collections');

test('makeMethodFromCollection', function (string $collection) {
    $firstCollection = $collection::make(['foo' => 'bar']);
    $secondCollection = $collection::make($firstCollection);
    $this->assertEquals(['foo' => 'bar'], $secondCollection->all());
})->with('collections');

test('makeMethodFromArray', function (string $collection) {
    $data = $collection::make(['foo' => 'bar']);
    $this->assertEquals(['foo' => 'bar'], $data->all());
})->with('collections');

test('wrapWithScalar', function (string $collection) {
    $data = $collection::wrap('foo');
    $this->assertEquals(['foo'], $data->all());
})->with('collections');

test('wrapWithArray', function (string $collection) {
    $data = $collection::wrap(['foo']);
    $this->assertEquals(['foo'], $data->all());
})->with('collections');

test('wrapWithArrayable', function (string $collection) {
    $data = $collection::wrap($o = new TestArrayableObject);
    $this->assertEquals([$o], $data->all());
})->with('collections');

test('wrapWithJsonable', function (string $collection) {
    $data = $collection::wrap($o = new TestJsonableObject);
    $this->assertEquals([$o], $data->all());
})->with('collections');

test('wrapWithJsonSerialize', function (string $collection) {
    $data = $collection::wrap($o = new TestJsonSerializeObject);
    $this->assertEquals([$o], $data->all());
})->with('collections');

test('wrapWithCollectionClass', function (string $collection) {
    $data = $collection::wrap($collection::make(['foo']));
    $this->assertEquals(['foo'], $data->all());
})->with('collections');

test('wrapWithCollectionSubclass', function (string $collection) {
    $data = TestCollectionSubclass::wrap($collection::make(['foo']));
    $this->assertEquals(['foo'], $data->all());
    $this->assertInstanceOf(TestCollectionSubclass::class, $data);
})->with('collections');

test('unwrapCollection', function (string $collection) {
    $data = new $collection(['foo']);
    $this->assertEquals(['foo'], $collection::unwrap($data));
})->with('collections');

test('unwrapCollectionWithArray', function (string $collection) {
    $this->assertEquals(['foo'], $collection::unwrap(['foo']));
})->with('collections');

test('unwrapCollectionWithScalar', function (string $collection) {
    $this->assertSame('foo', $collection::unwrap('foo'));
})->with('collections');

test('emptyMethod', function (string $collection) {
    $collection = $collection::empty();

    $this->assertCount(0, $collection->all());
})->with('collections');

test('timesMethod', function (string $collection) {
    $two = $collection::times(2, function ($number) {
        return 'slug-'.$number;
    });

    $zero = $collection::times(0, function ($number) {
        return 'slug-'.$number;
    });

    $negative = $collection::times(-4, function ($number) {
        return 'slug-'.$number;
    });

    $range = $collection::times(5);

    $this->assertEquals(['slug-1', 'slug-2'], $two->all());
    $this->assertTrue($zero->isEmpty());
    $this->assertTrue($negative->isEmpty());
    $this->assertEquals(range(1, 5), $range->all());
})->with('collections');

test('rangeMethod', function (string $collection) {
    $this->assertSame(
        [1, 2, 3, 4, 5],
        $collection::range(1, 5)->all()
    );

    $this->assertSame(
        [-2, -1, 0, 1, 2],
        $collection::range(-2, 2)->all()
    );

    $this->assertSame(
        [-4, -3, -2],
        $collection::range(-4, -2)->all()
    );

    $this->assertSame(
        [5, 4, 3, 2, 1],
        $collection::range(5, 1)->all()
    );

    $this->assertSame(
        [2, 1, 0, -1, -2],
        $collection::range(2, -2)->all()
    );

    $this->assertSame(
        [-2, -3, -4],
        $collection::range(-2, -4)->all()
    );
})->with('collections');

test('fromJson', function (string $collection) {
    $json = json_encode($array = ['foo' => 'bar', 'baz' => 'quz']);

    $instance = $collection::fromJson($json);

    $this->assertSame($array, $instance->toArray());
})->with('collections');

test('fromJsonWithDepth', function (string $collection) {
    $json = json_encode(['foo' => ['baz' => ['quz']], 'bar' => 'baz']);

    $instance = $collection::fromJson($json, 1);

    $this->assertEmpty($instance->toArray());
    $this->assertSame(JSON_ERROR_DEPTH, json_last_error());
})->with('collections');

test('fromJsonWithFlags', function (string $collection) {
    $instance = $collection::fromJson('{"int":99999999999999999999999}', 512, JSON_BIGINT_AS_STRING);

    $this->assertSame(['int' => '99999999999999999999999'], $instance->toArray());
})->with('collections');

test('constructMakeFromObject', function (string $collection) {
    $object = new stdClass;
    $object->foo = 'bar';
    $data = $collection::make($object);
    $this->assertEquals(['foo' => 'bar'], $data->all());
})->with('collections');

test('constructMethod', function (string $collection) {
    $data = new $collection('foo');
    $this->assertEquals(['foo'], $data->all());
})->with('collections');

test('constructMethodFromNull', function (string $collection) {
    $data = new $collection(null);
    $this->assertEquals([], $data->all());

    $data = new $collection;
    $this->assertEquals([], $data->all());
})->with('collections');

test('constructMethodFromCollection', function (string $collection) {
    $firstCollection = new $collection(['foo' => 'bar']);
    $secondCollection = new $collection($firstCollection);
    $this->assertEquals(['foo' => 'bar'], $secondCollection->all());
})->with('collections');

test('constructMethodFromArray', function (string $collection) {
    $data = new $collection(['foo' => 'bar']);
    $this->assertEquals(['foo' => 'bar'], $data->all());
})->with('collections');

test('constructMethodFromObject', function (string $collection) {
    $object = new stdClass;
    $object->foo = 'bar';
    $data = new $collection($object);
    $this->assertEquals(['foo' => 'bar'], $data->all());
})->with('collections');

test('constructMethodFromWeakMap', function (string $collection) {
    $map = new WeakMap();
    $object = new stdClass;
    $object->foo = 'bar';
    $map[$object] = 3;
    $data = new $collection($map);
    $this->assertEquals([3], $data->all());
})->with('collections');

test('splice', function () {
    $data = new Collection(['foo', 'baz']);
    $data->splice(1);
    $this->assertEquals(['foo'], $data->all());

    $data = new Collection(['foo', 'baz']);
    $data->splice(1, 0, 'bar');
    $this->assertEquals(['foo', 'bar', 'baz'], $data->all());

    $data = new Collection(['foo', 'baz']);
    $data->splice(1, 1);
    $this->assertEquals(['foo'], $data->all());

    $data = new Collection(['foo', 'baz']);
    $cut = $data->splice(1, 1, 'bar');
    $this->assertEquals(['foo', 'bar'], $data->all());
    $this->assertEquals(['baz'], $cut->all());

    $data = new Collection(['foo', 'baz']);
    $data->splice(1, 0, ['bar']);
    $this->assertEquals(['foo', 'bar', 'baz'], $data->all());

    $data = new Collection(['foo', 'baz']);
    $data->splice(1, 0, new Collection(['bar']));
    $this->assertEquals(['foo', 'bar', 'baz'], $data->all());
});
