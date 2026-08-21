<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (keys,
 * paginate/forPage, prepend, push, unshift, zip, pad, max/min, only,
 * select, avg, jsonSerialize, combine, concat, dump, reduce family,
 * random() exception).
 */

use Tests\Collections\Fixtures\TestArrayAccessImplementation;
use Tests\Collections\Fixtures\TestJsonSerializeToStringObject;
use Tests\NutsAndBolts\Fixtures\TestArrayableObject;
use Tests\NutsAndBolts\Fixtures\TestJsonableObject;
use Tests\NutsAndBolts\Fixtures\TestJsonSerializeObject;
use Voyager\NutsAndBolts\Collection;
use Symfony\Component\VarDumper\VarDumper;

test('keys', function (string $collection) {
    $c = new $collection(['name' => 'taylor', 'framework' => 'laravel']);
    $this->assertEquals(['name', 'framework'], $c->keys()->all());

    $c = new $collection(['taylor', 'laravel']);
    $this->assertEquals([0, 1], $c->keys()->all());
})->with('collections');

test('paginate', function (string $collection) {
    $c = new $collection(['one', 'two', 'three', 'four']);
    $this->assertEquals(['one', 'two'], $c->forPage(0, 2)->all());
    $this->assertEquals(['one', 'two'], $c->forPage(1, 2)->all());
    $this->assertEquals([2 => 'three', 3 => 'four'], $c->forPage(2, 2)->all());
    $this->assertEquals([], $c->forPage(3, 2)->all());
})->with('collections');

// Upstream marks this #[IgnoreDeprecations]; Pest's top-level function style has no
// direct equivalent attribute. Ported faithfully — flagged for the coordinator if it
// fails purely due to a deprecation notice being promoted to a warning.
test('prepend', function () {
    $c = new Collection(['one', 'two', 'three', 'four']);
    $this->assertEquals(
        ['zero', 'one', 'two', 'three', 'four'],
        $c->prepend('zero')->all()
    );

    $c = new Collection(['one' => 1, 'two' => 2]);
    $this->assertEquals(
        ['zero' => 0, 'one' => 1, 'two' => 2],
        $c->prepend(0, 'zero')->all()
    );

    $c = new Collection(['one' => 1, 'two' => 2]);
    $this->assertEquals(
        [null => 0, 'one' => 1, 'two' => 2],
        $c->prepend(0, null)->all()
    );

    $c = new Collection(['one' => 1, 'two' => 2]);
    $this->assertEquals(
        [null => 0, 'one' => 1, 'two' => 2],
        $c->prepend(0, '')->all()
    );
});

test('pushWithOneItem', function () {
    $expected = [
        0 => 4,
        1 => 5,
        2 => 6,
        3 => ['a', 'b', 'c'],
        4 => ['who' => 'Jonny', 'preposition' => 'from', 'where' => 'Laroe'],
        5 => 'Jonny from Laroe',
    ];

    $data = new Collection([4, 5, 6]);
    $data->push(['a', 'b', 'c']);
    $data->push(['who' => 'Jonny', 'preposition' => 'from', 'where' => 'Laroe']);
    $actual = $data->push('Jonny from Laroe')->toArray();

    $this->assertSame($expected, $actual);
});

test('pushWithMultipleItems', function () {
    $expected = [
        0 => 4,
        1 => 5,
        2 => 6,
        3 => 'Jonny',
        4 => 'from',
        5 => 'Laroe',
        6 => 'Jonny',
        7 => 'from',
        8 => 'Laroe',
        9 => 'a',
        10 => 'b',
        11 => 'c',
    ];

    $data = new Collection([4, 5, 6]);
    $data->push('Jonny', 'from', 'Laroe');
    $data->push(...[11 => 'Jonny', 12 => 'from', 13 => 'Laroe']);
    $data->push(...collect(['a', 'b', 'c']));
    $actual = $data->push(...[])->toArray();

    $this->assertSame($expected, $actual);
});

test('unshiftWithOneItem', function () {
    $expected = [
        0 => 'Jonny from Laroe',
        1 => ['who' => 'Jonny', 'preposition' => 'from', 'where' => 'Laroe'],
        2 => ['a', 'b', 'c'],
        3 => 4,
        4 => 5,
        5 => 6,
    ];

    $data = new Collection([4, 5, 6]);
    $data->unshift(['a', 'b', 'c']);
    $data->unshift(['who' => 'Jonny', 'preposition' => 'from', 'where' => 'Laroe']);
    $actual = $data->unshift('Jonny from Laroe')->toArray();

    $this->assertSame($expected, $actual);
});

test('unshiftWithMultipleItems', function () {
    $expected = [
        0 => 'a',
        1 => 'b',
        2 => 'c',
        3 => 'Jonny',
        4 => 'from',
        5 => 'Laroe',
        6 => 'Jonny',
        7 => 'from',
        8 => 'Laroe',
        9 => 4,
        10 => 5,
        11 => 6,
    ];

    $data = new Collection([4, 5, 6]);
    $data->unshift('Jonny', 'from', 'Laroe');
    $data->unshift(...[11 => 'Jonny', 12 => 'from', 13 => 'Laroe']);
    $data->unshift(...collect(['a', 'b', 'c']));
    $actual = $data->unshift(...[])->toArray();

    $this->assertSame($expected, $actual);
});

test('zip', function (string $collection) {
    $c = new $collection([1, 2, 3]);
    $c = $c->zip(new $collection([4, 5, 6]));
    $this->assertInstanceOf($collection, $c);
    $this->assertInstanceOf($collection, $c->get(0));
    $this->assertInstanceOf($collection, $c->get(1));
    $this->assertInstanceOf($collection, $c->get(2));
    $this->assertCount(3, $c);
    $this->assertEquals([1, 4], $c->get(0)->all());
    $this->assertEquals([2, 5], $c->get(1)->all());
    $this->assertEquals([3, 6], $c->get(2)->all());

    $c = new $collection([1, 2, 3]);
    $c = $c->zip([4, 5, 6], [7, 8, 9]);
    $this->assertCount(3, $c);
    $this->assertEquals([1, 4, 7], $c->get(0)->all());
    $this->assertEquals([2, 5, 8], $c->get(1)->all());
    $this->assertEquals([3, 6, 9], $c->get(2)->all());

    $c = new $collection([1, 2, 3]);
    $c = $c->zip([4, 5, 6], [7]);
    $this->assertCount(3, $c);
    $this->assertEquals([1, 4, 7], $c->get(0)->all());
    $this->assertEquals([2, 5, null], $c->get(1)->all());
    $this->assertEquals([3, 6, null], $c->get(2)->all());
})->with('collections');

test('padPadsArrayWithValue', function (string $collection) {
    $c = new $collection([1, 2, 3]);
    $c = $c->pad(4, 0);
    $this->assertEquals([1, 2, 3, 0], $c->all());

    $c = new $collection([1, 2, 3, 4, 5]);
    $c = $c->pad(4, 0);
    $this->assertEquals([1, 2, 3, 4, 5], $c->all());

    $c = new $collection([1, 2, 3]);
    $c = $c->pad(-4, 0);
    $this->assertEquals([0, 1, 2, 3], $c->all());

    $c = new $collection([1, 2, 3, 4, 5]);
    $c = $c->pad(-4, 0);
    $this->assertEquals([1, 2, 3, 4, 5], $c->all());
})->with('collections');

test('gettingMaxItemsFromCollection', function (string $collection) {
    $c = new $collection([(object) ['foo' => 10], (object) ['foo' => 20]]);
    $this->assertEquals(20, $c->max(function ($item) {
        return $item->foo;
    }));
    $this->assertEquals(20, $c->max('foo'));
    $this->assertEquals(20, $c->max->foo);

    $c = new $collection([['foo' => 10], ['foo' => 20]]);
    $this->assertEquals(20, $c->max('foo'));
    $this->assertEquals(20, $c->max->foo);

    $c = new $collection([1, 2, 3, 4, 5]);
    $this->assertEquals(5, $c->max());

    $c = new $collection;
    $this->assertNull($c->max());
})->with('collections');

test('gettingMinItemsFromCollection', function (string $collection) {
    $c = new $collection([(object) ['foo' => 10], (object) ['foo' => 20]]);
    $this->assertEquals(10, $c->min(function ($item) {
        return $item->foo;
    }));
    $this->assertEquals(10, $c->min('foo'));
    $this->assertEquals(10, $c->min->foo);

    $c = new $collection([['foo' => 10], ['foo' => 20]]);
    $this->assertEquals(10, $c->min('foo'));
    $this->assertEquals(10, $c->min->foo);

    $c = new $collection([['foo' => 10], ['foo' => 20], ['foo' => null]]);
    $this->assertEquals(10, $c->min('foo'));
    $this->assertEquals(10, $c->min->foo);

    $c = new $collection([1, 2, 3, 4, 5]);
    $this->assertEquals(1, $c->min());

    $c = new $collection([1, null, 3, 4, 5]);
    $this->assertEquals(1, $c->min());

    $c = new $collection([0, 1, 2, 3, 4]);
    $this->assertEquals(0, $c->min());

    $c = new $collection;
    $this->assertNull($c->min());
})->with('collections');

test('only', function (string $collection) {
    $data = new $collection(['first' => 'Taylor', 'last' => 'Otwell', 'email' => 'taylorotwell@gmail.com']);

    $this->assertEquals($data->all(), $data->only(null)->all());
    $this->assertEquals(['first' => 'Taylor'], $data->only(['first', 'missing'])->all());
    $this->assertEquals(['first' => 'Taylor'], $data->only('first', 'missing')->all());
    $this->assertEquals(['first' => 'Taylor'], $data->only(collect(['first', 'missing']))->all());

    $this->assertEquals(['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'], $data->only(['first', 'email'])->all());
    $this->assertEquals(['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'], $data->only('first', 'email')->all());
    $this->assertEquals(['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'], $data->only(collect(['first', 'email']))->all());
})->with('collections');

test('selectWithArrays', function (string $collection) {
    $data = new $collection([
        ['first' => 'Taylor', 'last' => 'Otwell', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'last' => 'Archer', 'email' => 'jessarcher@gmail.com'],
    ]);

    $this->assertEquals($data->all(), $data->select(null)->all());
    $this->assertEquals([['first' => 'Taylor'], ['first' => 'Jess']], $data->select(['first', 'missing'])->all());
    $this->assertEquals([['first' => 'Taylor'], ['first' => 'Jess']], $data->select('first', 'missing')->all());
    $this->assertEquals([['first' => 'Taylor'], ['first' => 'Jess']], $data->select(collect(['first', 'missing']))->all());

    $this->assertEquals([
        ['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'email' => 'jessarcher@gmail.com'],
    ], $data->select(['first', 'email'])->all());

    $this->assertEquals([
        ['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'email' => 'jessarcher@gmail.com'],
    ], $data->select('first', 'email')->all());

    $this->assertEquals([
        ['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'email' => 'jessarcher@gmail.com'],
    ], $data->select(collect(['first', 'email']))->all());
})->with('collections');

test('selectWithArrayAccess', function (string $collection) {
    $data = new $collection([
        new TestArrayAccessImplementation(['first' => 'Taylor', 'last' => 'Otwell', 'email' => 'taylorotwell@gmail.com']),
        new TestArrayAccessImplementation(['first' => 'Jess', 'last' => 'Archer', 'email' => 'jessarcher@gmail.com']),
    ]);

    $this->assertEquals($data->all(), $data->select(null)->all());
    $this->assertEquals([['first' => 'Taylor'], ['first' => 'Jess']], $data->select(['first', 'missing'])->all());
    $this->assertEquals([['first' => 'Taylor'], ['first' => 'Jess']], $data->select('first', 'missing')->all());
    $this->assertEquals([['first' => 'Taylor'], ['first' => 'Jess']], $data->select(collect(['first', 'missing']))->all());

    $this->assertEquals([
        ['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'email' => 'jessarcher@gmail.com'],
    ], $data->select(['first', 'email'])->all());

    $this->assertEquals([
        ['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'email' => 'jessarcher@gmail.com'],
    ], $data->select('first', 'email')->all());

    $this->assertEquals([
        ['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'email' => 'jessarcher@gmail.com'],
    ], $data->select(collect(['first', 'email']))->all());
})->with('collections');

test('selectWithObjects', function (string $collection) {
    $data = new $collection([
        (object) ['first' => 'Taylor', 'last' => 'Otwell', 'email' => 'taylorotwell@gmail.com'],
        (object) ['first' => 'Jess', 'last' => 'Archer', 'email' => 'jessarcher@gmail.com'],
    ]);

    $this->assertEquals($data->all(), $data->select(null)->all());
    $this->assertEquals([['first' => 'Taylor'], ['first' => 'Jess']], $data->select(['first', 'missing'])->all());
    $this->assertEquals([['first' => 'Taylor'], ['first' => 'Jess']], $data->select('first', 'missing')->all());
    $this->assertEquals([['first' => 'Taylor'], ['first' => 'Jess']], $data->select(collect(['first', 'missing']))->all());

    $this->assertEquals([
        ['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'email' => 'jessarcher@gmail.com'],
    ], $data->select(['first', 'email'])->all());

    $this->assertEquals([
        ['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'email' => 'jessarcher@gmail.com'],
    ], $data->select('first', 'email')->all());

    $this->assertEquals([
        ['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'],
        ['first' => 'Jess', 'email' => 'jessarcher@gmail.com'],
    ], $data->select(collect(['first', 'email']))->all());
})->with('collections');

test('gettingAvgItemsFromCollection', function (string $collection) {
    $c = new $collection([(object) ['foo' => 10], (object) ['foo' => 20]]);
    $this->assertEquals(15, $c->avg(function ($item) {
        return $item->foo;
    }));
    $this->assertEquals(15, $c->avg('foo'));
    $this->assertEquals(15, $c->avg->foo);

    $c = new $collection([(object) ['foo' => 10], (object) ['foo' => 20], (object) ['foo' => null]]);
    $this->assertEquals(15, $c->avg(function ($item) {
        return $item->foo;
    }));
    $this->assertEquals(15, $c->avg('foo'));
    $this->assertEquals(15, $c->avg->foo);

    $c = new $collection([['foo' => 10], ['foo' => 20]]);
    $this->assertEquals(15, $c->avg('foo'));
    $this->assertEquals(15, $c->avg->foo);

    $c = new $collection([1, 2, 3, 4, 5]);
    $this->assertEquals(3, $c->avg());

    $c = new $collection;
    $this->assertNull($c->avg());

    $c = new $collection([['foo' => '4'], ['foo' => '2']]);
    $this->assertIsInt($c->avg('foo'));
    $this->assertEquals(3, $c->avg('foo'));

    $c = new $collection([['foo' => 1], ['foo' => 2]]);
    $this->assertIsFloat($c->avg('foo'));
    $this->assertEquals(1.5, $c->avg('foo'));

    $c = new $collection([
        ['foo' => 1], ['foo' => 2],
        (object) ['foo' => 6],
    ]);
    $this->assertEquals(3, $c->avg('foo'));

    $c = new $collection([0]);
    $this->assertEquals(0, $c->avg());
})->with('collections');

test('jsonSerialize', function (string $collection) {
    $c = new $collection([
        new TestArrayableObject,
        new TestJsonableObject,
        new TestJsonSerializeObject,
        new TestJsonSerializeToStringObject,
        'baz',
    ]);

    $this->assertSame([
        ['foo' => 'bar'],
        ['foo' => 'bar'],
        ['foo' => 'bar'],
        'foobar',
        'baz',
    ], $c->jsonSerialize());
})->with('collections');

test('combineWithArray', function (string $collection) {
    $c = new $collection([1, 2, 3]);
    $actual = $c->combine([4, 5, 6])->toArray();
    $expected = [
        1 => 4,
        2 => 5,
        3 => 6,
    ];

    $this->assertSame($expected, $actual);

    $c = new $collection(['name', 'family']);
    $actual = $c->combine([1 => 'taylor', 2 => 'otwell'])->toArray();
    $expected = [
        'name' => 'taylor',
        'family' => 'otwell',
    ];

    $this->assertSame($expected, $actual);

    $c = new $collection([1 => 'name', 2 => 'family']);
    $actual = $c->combine(['taylor', 'otwell'])->toArray();
    $expected = [
        'name' => 'taylor',
        'family' => 'otwell',
    ];

    $this->assertSame($expected, $actual);

    $c = new $collection([1 => 'name', 2 => 'family']);
    $actual = $c->combine([2 => 'taylor', 3 => 'otwell'])->toArray();
    $expected = [
        'name' => 'taylor',
        'family' => 'otwell',
    ];

    $this->assertSame($expected, $actual);
})->with('collections');

test('combineWithCollection', function (string $collection) {
    $expected = [
        1 => 4,
        2 => 5,
        3 => 6,
    ];

    $keyCollection = new $collection(array_keys($expected));
    $valueCollection = new $collection(array_values($expected));
    $actual = $keyCollection->combine($valueCollection)->toArray();

    $this->assertSame($expected, $actual);
})->with('collections');

test('concatWithArray', function (string $collection) {
    $expected = [
        0 => 4,
        1 => 5,
        2 => 6,
        3 => 'a',
        4 => 'b',
        5 => 'c',
        6 => 'Jonny',
        7 => 'from',
        8 => 'Laroe',
        9 => 'Jonny',
        10 => 'from',
        11 => 'Laroe',
    ];

    $data = new $collection([4, 5, 6]);
    $data = $data->concat(['a', 'b', 'c']);
    $data = $data->concat(['who' => 'Jonny', 'preposition' => 'from', 'where' => 'Laroe']);
    $actual = $data->concat(['who' => 'Jonny', 'preposition' => 'from', 'where' => 'Laroe'])->toArray();

    $this->assertSame($expected, $actual);
})->with('collections');

test('concatWithCollection', function (string $collection) {
    $expected = [
        0 => 4,
        1 => 5,
        2 => 6,
        3 => 'a',
        4 => 'b',
        5 => 'c',
        6 => 'Jonny',
        7 => 'from',
        8 => 'Laroe',
        9 => 'Jonny',
        10 => 'from',
        11 => 'Laroe',
    ];

    $firstCollection = new $collection([4, 5, 6]);
    $secondCollection = new $collection(['a', 'b', 'c']);
    $thirdCollection = new $collection(['who' => 'Jonny', 'preposition' => 'from', 'where' => 'Laroe']);
    $firstCollection = $firstCollection->concat($secondCollection);
    $firstCollection = $firstCollection->concat($thirdCollection);
    $actual = $firstCollection->concat($thirdCollection)->toArray();

    $this->assertSame($expected, $actual);
})->with('collections');

test('dump', function (string $collection) {
    $log = new Collection;

    VarDumper::setHandler(function ($value) use ($log) {
        $log->add($value);
    });

    (new $collection([1, 2, 3]))->dump('one', 'two');

    $this->assertSame([[1, 2, 3], 'one', 'two'], $log->all());

    VarDumper::setHandler(null);
})->with('collections');

test('reduce', function (string $collection) {
    $data = new $collection([1, 2, 3]);
    $this->assertEquals(6, $data->reduce(function ($carry, $element) {
        return $carry += $element;
    }));

    $data = new $collection([
        'foo' => 'bar',
        'baz' => 'qux',
    ]);
    $this->assertSame('foobarbazqux', $data->reduce(function ($carry, $element, $key) {
        return $carry .= $key.$element;
    }));
})->with('collections');

test('reduceSpread', function (string $collection) {
    $data = new $collection([-1, 0, 1, 2, 3, 4, 5]);

    [$sum, $max, $min] = $data->reduceSpread(function ($sum, $max, $min, $value) {
        $sum += $value;
        $max = max($max, $value);
        $min = min($min, $value);

        return [$sum, $max, $min];
    }, 0, PHP_INT_MIN, PHP_INT_MAX);

    $this->assertEquals(14, $sum);
    $this->assertEquals(5, $max);
    $this->assertEquals(-1, $min);
})->with('collections');

test('reduceSpreadThrowsAnExceptionIfReducerDoesNotReturnAnArray', function (string $collection) {
    $data = new $collection([1]);

    $this->expectException(UnexpectedValueException::class);

    $data->reduceSpread(function () {
        return false;
    }, null);
})->with('collections');

test('randomThrowsAnExceptionUsingAmountBiggerThanCollectionSize', function (string $collection) {
    $this->expectException(InvalidArgumentException::class);

    $data = new $collection([1, 2, 3]);
    $data->random(4);
})->with('collections');
