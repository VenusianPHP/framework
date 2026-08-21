<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (pipe family,
 * median, mode, slice family, construction from Traversable/enum, split
 * family, higher-order proxy calls on groupBy/map/first).
 */

use Tests\Collections\Fixtures\TestCollectionMapIntoObject;
use Tests\Collections\Fixtures\TestSupportCollectionHigherOrderItem;
use Tests\Collections\Fixtures\TestSupportCollectionHigherOrderStaticClass1;
use Tests\Collections\Fixtures\TestSupportCollectionHigherOrderStaticClass2;
use Tests\NutsAndBolts\TestBackedEnum;
use Tests\NutsAndBolts\TestEnum;
use Voyager\NutsAndBolts\Collection;

test('pipe', function (string $collection) {
    $data = new $collection([1, 2, 3]);

    $this->assertEquals(6, $data->pipe(function ($data) {
        return $data->sum();
    }));
})->with('collections');

test('pipeInto', function (string $collection) {
    $data = new $collection([
        'first', 'second',
    ]);

    $instance = $data->pipeInto(TestCollectionMapIntoObject::class);

    $this->assertSame($data, $instance->value);
})->with('collections');

test('pipeThrough', function (string $collection) {
    $data = new $collection([1, 2, 3]);

    $result = $data->pipeThrough([
        function ($data) {
            return $data->merge([4, 5]);
        },
        function ($data) {
            return $data->sum();
        },
    ]);

    $this->assertEquals(15, $result);
})->with('collections');

test('medianValueWithArrayCollection', function (string $collection) {
    $data = new $collection([1, 2, 2, 4]);

    $this->assertEquals(2, $data->median());
})->with('collections');

test('medianValueByKey', function (string $collection) {
    $data = new $collection([
        (object) ['foo' => 1],
        (object) ['foo' => 2],
        (object) ['foo' => 2],
        (object) ['foo' => 4],
    ]);
    $this->assertEquals(2, $data->median('foo'));
})->with('collections');

test('medianOnCollectionWithNull', function (string $collection) {
    $data = new $collection([
        (object) ['foo' => 1],
        (object) ['foo' => 2],
        (object) ['foo' => 4],
        (object) ['foo' => null],
    ]);
    $this->assertEquals(2, $data->median('foo'));
})->with('collections');

test('evenMedianCollection', function (string $collection) {
    $data = new $collection([
        (object) ['foo' => 0],
        (object) ['foo' => 3],
    ]);
    $this->assertEquals(1.5, $data->median('foo'));
})->with('collections');

test('medianOutOfOrderCollection', function (string $collection) {
    $data = new $collection([
        (object) ['foo' => 0],
        (object) ['foo' => 5],
        (object) ['foo' => 3],
    ]);
    $this->assertEquals(3, $data->median('foo'));
})->with('collections');

test('medianOnEmptyCollectionReturnsNull', function (string $collection) {
    $data = new $collection;
    $this->assertNull($data->median());
})->with('collections');

test('modeOnNullCollection', function (string $collection) {
    $data = new $collection;
    $this->assertNull($data->mode());
})->with('collections');

test('mode', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 4, 5]);
    $this->assertIsArray($data->mode());
    $this->assertEquals([4], $data->mode());
})->with('collections');

test('modeValueByKey', function (string $collection) {
    $data = new $collection([
        (object) ['foo' => 1],
        (object) ['foo' => 1],
        (object) ['foo' => 2],
        (object) ['foo' => 4],
    ]);
    $data2 = new Collection([
        ['foo' => 1],
        ['foo' => 1],
        ['foo' => 2],
        ['foo' => 4],
    ]);
    $this->assertEquals([1], $data->mode('foo'));
    $this->assertEquals($data2->mode('foo'), $data->mode('foo'));
})->with('collections');

test('withMultipleModeValues', function (string $collection) {
    $data = new $collection([1, 2, 2, 1]);
    $this->assertEquals([1, 2], $data->mode());
})->with('collections');

test('sliceOffset', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8]);
    $this->assertEquals([4, 5, 6, 7, 8], $data->slice(3)->values()->toArray());
})->with('collections');

test('sliceNegativeOffset', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8]);
    $this->assertEquals([6, 7, 8], $data->slice(-3)->values()->toArray());
})->with('collections');

test('sliceOffsetAndLength', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8]);
    $this->assertEquals([4, 5, 6], $data->slice(3, 3)->values()->toArray());
})->with('collections');

test('sliceOffsetAndNegativeLength', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8]);
    $this->assertEquals([4, 5, 6, 7], $data->slice(3, -1)->values()->toArray());
})->with('collections');

test('sliceNegativeOffsetAndLength', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8]);
    $this->assertEquals([4, 5, 6], $data->slice(-5, 3)->values()->toArray());
})->with('collections');

test('sliceNegativeOffsetAndNegativeLength', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8]);
    $this->assertEquals([3, 4, 5, 6], $data->slice(-6, -2)->values()->toArray());
})->with('collections');

test('collectionFromTraversable', function (string $collection) {
    $data = new $collection(new ArrayObject([1, 2, 3]));
    $this->assertEquals([1, 2, 3], $data->toArray());
})->with('collections');

test('collectionFromTraversableWithKeys', function (string $collection) {
    $data = new $collection(new ArrayObject(['foo' => 1, 'bar' => 2, 'baz' => 3]));
    $this->assertEquals(['foo' => 1, 'bar' => 2, 'baz' => 3], $data->toArray());
})->with('collections');

test('collectionFromEnum', function (string $collection) {
    $data = new $collection(TestEnum::A);
    $this->assertEquals([TestEnum::A], $data->toArray());
})->with('collections');

test('collectionFromBackedEnum', function (string $collection) {
    $data = new $collection(TestBackedEnum::A);
    $this->assertEquals([TestBackedEnum::A], $data->toArray());
})->with('collections');

test('splitCollectionWithADivisibleCount', function (string $collection) {
    $data = new $collection(['a', 'b', 'c', 'd']);
    $split = $data->split(2);

    $this->assertSame(['a', 'b'], $split->get(0)->all());
    $this->assertSame(['c', 'd'], $split->get(1)->all());
    $this->assertInstanceOf($collection, $split);

    $this->assertEquals(
        [['a', 'b'], ['c', 'd']],
        $data->split(2)->map(function (Collection $chunk) {
            return $chunk->values()->toArray();
        })->toArray()
    );

    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    $split = $data->split(2);

    $this->assertSame([1, 2, 3, 4, 5], $split->get(0)->all());
    $this->assertSame([6, 7, 8, 9, 10], $split->get(1)->all());

    $this->assertEquals(
        [[1, 2, 3, 4, 5], [6, 7, 8, 9, 10]],
        $data->split(2)->map(function (Collection $chunk) {
            return $chunk->values()->toArray();
        })->toArray()
    );
})->with('collections');

test('splitCollectionWithAnUndivisableCount', function (string $collection) {
    $data = new $collection(['a', 'b', 'c']);
    $split = $data->split(2);

    $this->assertSame(['a', 'b'], $split->get(0)->all());
    $this->assertSame(['c'], $split->get(1)->all());

    $this->assertEquals(
        [['a', 'b'], ['c']],
        $data->split(2)->map(function (Collection $chunk) {
            return $chunk->values()->toArray();
        })->toArray()
    );
})->with('collections');

test('splitCollectionWithCountLessThenDivisor', function (string $collection) {
    $data = new $collection(['a']);
    $split = $data->split(2);

    $this->assertSame(['a'], $split->get(0)->all());
    $this->assertNull($split->get(1));

    $this->assertEquals(
        [['a']],
        $data->split(2)->map(function (Collection $chunk) {
            return $chunk->values()->toArray();
        })->toArray()
    );
})->with('collections');

test('splitCollectionIntoThreeWithCountOfFour', function (string $collection) {
    $data = new $collection(['a', 'b', 'c', 'd']);
    $split = $data->split(3);

    $this->assertSame(['a', 'b'], $split->get(0)->all());
    $this->assertSame(['c'], $split->get(1)->all());
    $this->assertSame(['d'], $split->get(2)->all());

    $this->assertEquals(
        [['a', 'b'], ['c'], ['d']],
        $data->split(3)->map(function (Collection $chunk) {
            return $chunk->values()->toArray();
        })->toArray()
    );
})->with('collections');

test('splitCollectionIntoThreeWithCountOfFive', function (string $collection) {
    $data = new $collection(['a', 'b', 'c', 'd', 'e']);
    $split = $data->split(3);

    $this->assertSame(['a', 'b'], $split->get(0)->all());
    $this->assertSame(['c', 'd'], $split->get(1)->all());
    $this->assertSame(['e'], $split->get(2)->all());

    $this->assertEquals(
        [['a', 'b'], ['c', 'd'], ['e']],
        $data->split(3)->map(function (Collection $chunk) {
            return $chunk->values()->toArray();
        })->toArray()
    );
})->with('collections');

test('splitCollectionIntoSixWithCountOfTen', function (string $collection) {
    $data = new $collection(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j']);
    $split = $data->split(6);

    $this->assertSame(['a', 'b'], $split->get(0)->all());
    $this->assertSame(['c', 'd'], $split->get(1)->all());
    $this->assertSame(['e', 'f'], $split->get(2)->all());
    $this->assertSame(['g', 'h'], $split->get(3)->all());
    $this->assertSame(['i'], $split->get(4)->all());
    $this->assertSame(['j'], $split->get(5)->all());

    $this->assertEquals(
        [['a', 'b'], ['c', 'd'], ['e', 'f'], ['g', 'h'], ['i'], ['j']],
        $data->split(6)->map(function (Collection $chunk) {
            return $chunk->values()->toArray();
        })->toArray()
    );
})->with('collections');

test('splitEmptyCollection', function (string $collection) {
    $data = new $collection;
    $split = $data->split(2);

    $this->assertNull($split->get(0));
    $this->assertNull($split->get(1));

    $this->assertEquals(
        [],
        $data->split(2)->map(function (Collection $chunk) {
            return $chunk->values()->toArray();
        })->toArray()
    );
})->with('collections');

test('higherOrderCollectionGroupBy', function (string $collection) {
    $data = new $collection([
        new TestSupportCollectionHigherOrderItem,
        new TestSupportCollectionHigherOrderItem('TAYLOR'),
        new TestSupportCollectionHigherOrderItem('foo'),
    ]);

    $this->assertEquals([
        'taylor' => [$data->get(0)],
        'TAYLOR' => [$data->get(1)],
        'foo' => [$data->get(2)],
    ], $data->groupBy->name->toArray());

    $this->assertEquals([
        'TAYLOR' => [$data->get(0), $data->get(1)],
        'FOO' => [$data->get(2)],
    ], $data->groupBy->uppercase()->toArray());
})->with('collections');

test('higherOrderCollectionMap', function (string $collection) {
    $person1 = (object) ['name' => 'Taylor'];
    $person2 = (object) ['name' => 'Yaz'];

    $data = new $collection([$person1, $person2]);

    $this->assertEquals(['Taylor', 'Yaz'], $data->map->name->toArray());

    $data = new $collection([new TestSupportCollectionHigherOrderItem, new TestSupportCollectionHigherOrderItem]);

    $this->assertEquals(['TAYLOR', 'TAYLOR'], $data->each->uppercase()->map->name->toArray());
})->with('collections');

test('higherOrderCollectionMapFromArrays', function (string $collection) {
    $person1 = ['name' => 'Taylor'];
    $person2 = ['name' => 'Yaz'];

    $data = new $collection([$person1, $person2]);

    $this->assertEquals(['Taylor', 'Yaz'], $data->map->name->toArray());

    $data = new $collection([new TestSupportCollectionHigherOrderItem, new TestSupportCollectionHigherOrderItem]);

    $this->assertEquals(['TAYLOR', 'TAYLOR'], $data->each->uppercase()->map->name->toArray());
})->with('collections');

test('higherOrderCollectionStaticCall', function (string $collection) {
    $class1 = TestSupportCollectionHigherOrderStaticClass1::class;
    $class2 = TestSupportCollectionHigherOrderStaticClass2::class;

    $classes = new $collection([$class1, $class2]);

    $this->assertEquals(['TAYLOR', 't a y l o r'], $classes->map->transform('taylor')->toArray());
    $this->assertEquals($class1, $classes->first->matches('Taylor'));
    $this->assertEquals($class2, $classes->first->matches('Otwell'));
})->with('collections');
