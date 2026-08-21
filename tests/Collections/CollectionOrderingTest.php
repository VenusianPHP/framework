<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (collapse,
 * join, crossJoin, sort family, sortBy family, sortKeys family, reverse,
 * flip, chunk family, splitIn).
 */

test('collapse', function (string $collection) {
    // Normal case: a two-dimensional array with different elements
    $data = new $collection([[$object1 = new stdClass], [$object2 = new stdClass]]);
    $this->assertEquals([$object1, $object2], $data->collapse()->all());

    // Case including numeric and string elements
    $data = new $collection([[1], [2], [3], ['foo', 'bar'], new $collection(['baz', 'boom'])]);
    $this->assertEquals([1, 2, 3, 'foo', 'bar', 'baz', 'boom'], $data->collapse()->all());

    // Case with empty two-dimensional arrays
    $data = new $collection([[], [], []]);
    $this->assertEquals([], $data->collapse()->all());

    // Case with both empty arrays and arrays with elements
    $data = new $collection([[], [1, 2], [], ['foo', 'bar']]);
    $this->assertEquals([1, 2, 'foo', 'bar'], $data->collapse()->all());

    // Case including collections and arrays
    $collection = new $collection(['baz', 'boom']);
    $data = new $collection([[1], [2], [3], ['foo', 'bar'], $collection]);
    $this->assertEquals([1, 2, 3, 'foo', 'bar', 'baz', 'boom'], $data->collapse()->all());
})->with('collections');

test('collapseWithNestedCollections', function (string $collection) {
    $data = new $collection([new $collection([1, 2, 3]), new $collection([4, 5, 6])]);
    $this->assertEquals([1, 2, 3, 4, 5, 6], $data->collapse()->all());
})->with('collections');

test('collapseWithKeys', function (string $collection) {
    $data = new $collection([[1 => 'a'], [3 => 'c'], [2 => 'b'], 'drop']);
    $this->assertEquals([1 => 'a', 3 => 'c', 2 => 'b'], $data->collapseWithKeys()->all());

    // Case with an already flat collection
    $data = new $collection(['a', 'b', 'c']);
    $this->assertEquals([], $data->collapseWithKeys()->all());
})->with('collections');

test('collapseWithKeysOnNestedCollections', function (string $collection) {
    $data = new $collection([new $collection(['a' => '1a', 'b' => '1b']), new $collection(['b' => '2b', 'c' => '2c']), 'drop']);
    $this->assertEquals(['a' => '1a', 'b' => '2b', 'c' => '2c'], $data->collapseWithKeys()->all());
})->with('collections');

test('join', function (string $collection) {
    $this->assertSame('a, b, c', (new $collection(['a', 'b', 'c']))->join(', '));

    $this->assertSame('a, b and c', (new $collection(['a', 'b', 'c']))->join(', ', ' and '));

    $this->assertSame('a and b', (new $collection(['a', 'b']))->join(', ', ' and '));

    $this->assertSame('a', (new $collection(['a']))->join(', ', ' and '));

    $this->assertSame('', (new $collection([]))->join(', ', ' and '));
})->with('collections');

test('crossJoin', function (string $collection) {
    // Cross join with an array
    $this->assertEquals(
        [[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']],
        (new $collection([1, 2]))->crossJoin(['a', 'b'])->all()
    );

    // Cross join with a collection
    $this->assertEquals(
        [[1, 'a'], [1, 'b'], [2, 'a'], [2, 'b']],
        (new $collection([1, 2]))->crossJoin(new $collection(['a', 'b']))->all()
    );

    // Cross join with 2 collections
    $this->assertEquals(
        [
            [1, 'a', 'I'], [1, 'a', 'II'],
            [1, 'b', 'I'], [1, 'b', 'II'],
            [2, 'a', 'I'], [2, 'a', 'II'],
            [2, 'b', 'I'], [2, 'b', 'II'],
        ],
        (new $collection([1, 2]))->crossJoin(
            new $collection(['a', 'b']),
            new $collection(['I', 'II'])
        )->all()
    );
})->with('collections');

test('sort', function (string $collection) {
    $data = (new $collection([5, 3, 1, 2, 4]))->sort();
    $this->assertEquals([1, 2, 3, 4, 5], $data->values()->all());

    $data = (new $collection([-1, -3, -2, -4, -5, 0, 5, 3, 1, 2, 4]))->sort();
    $this->assertEquals([-5, -4, -3, -2, -1, 0, 1, 2, 3, 4, 5], $data->values()->all());

    $data = (new $collection(['foo', 'bar-10', 'bar-1']))->sort();
    $this->assertEquals(['bar-1', 'bar-10', 'foo'], $data->values()->all());

    $data = (new $collection(['T2', 'T1', 'T10']))->sort();
    $this->assertEquals(['T1', 'T10', 'T2'], $data->values()->all());

    $data = (new $collection(['T2', 'T1', 'T10']))->sort(SORT_NATURAL);
    $this->assertEquals(['T1', 'T2', 'T10'], $data->values()->all());
})->with('collections');

test('sortDesc', function (string $collection) {
    $data = (new $collection([5, 3, 1, 2, 4]))->sortDesc();
    $this->assertEquals([5, 4, 3, 2, 1], $data->values()->all());

    $data = (new $collection([-1, -3, -2, -4, -5, 0, 5, 3, 1, 2, 4]))->sortDesc();
    $this->assertEquals([5, 4, 3, 2, 1, 0, -1, -2, -3, -4, -5], $data->values()->all());

    $data = (new $collection(['bar-1', 'foo', 'bar-10']))->sortDesc();
    $this->assertEquals(['foo', 'bar-10', 'bar-1'], $data->values()->all());

    $data = (new $collection(['T2', 'T1', 'T10']))->sortDesc();
    $this->assertEquals(['T2', 'T10', 'T1'], $data->values()->all());

    $data = (new $collection(['T2', 'T1', 'T10']))->sortDesc(SORT_NATURAL);
    $this->assertEquals(['T10', 'T2', 'T1'], $data->values()->all());
})->with('collections');

test('sortWithCallback', function (string $collection) {
    $data = (new $collection([5, 3, 1, 2, 4]))->sort(function ($a, $b) {
        if ($a === $b) {
            return 0;
        }

        return ($a < $b) ? -1 : 1;
    });

    $this->assertEquals(range(1, 5), array_values($data->all()));
})->with('collections');

test('sortBy', function (string $collection) {
    $data = new $collection(['taylor', 'dayle']);
    $data = $data->sortBy(function ($x) {
        return $x;
    });

    $this->assertEquals(['dayle', 'taylor'], array_values($data->all()));

    $data = new $collection(['dayle', 'taylor']);
    $data = $data->sortByDesc(function ($x) {
        return $x;
    });

    $this->assertEquals(['taylor', 'dayle'], array_values($data->all()));
})->with('collections');

test('sortByString', function (string $collection) {
    $data = new $collection([['name' => 'taylor'], ['name' => 'dayle']]);
    $data = $data->sortBy('name', SORT_STRING);

    $this->assertEquals([['name' => 'dayle'], ['name' => 'taylor']], array_values($data->all()));

    $data = new $collection([['name' => 'taylor'], ['name' => 'dayle']]);
    $data = $data->sortBy('name', SORT_STRING, true);

    $this->assertEquals([['name' => 'taylor'], ['name' => 'dayle']], array_values($data->all()));
})->with('collections');

test('sortByCallableString', function (string $collection) {
    $data = new $collection([['sort' => 2], ['sort' => 1]]);
    $data = $data->sortBy([['sort', 'asc']]);

    $this->assertEquals([['sort' => 1], ['sort' => 2]], array_values($data->all()));
})->with('collections');

test('sortByCallableStringDesc', function (string $collection) {
    $data = new $collection([['id' => 1, 'name' => 'foo'], ['id' => 2, 'name' => 'bar']]);
    $data = $data->sortByDesc(['id']);
    $this->assertEquals([['id' => 2, 'name' => 'bar'], ['id' => 1, 'name' => 'foo']], array_values($data->all()));

    $data = new $collection([['id' => 1, 'name' => 'foo'], ['id' => 2, 'name' => 'bar'], ['id' => 2, 'name' => 'baz']]);
    $data = $data->sortByDesc(['id']);
    $this->assertEquals([['id' => 2, 'name' => 'bar'], ['id' => 2, 'name' => 'baz'], ['id' => 1, 'name' => 'foo']], array_values($data->all()));

    $data = $data->sortByDesc(['id', 'name']);
    $this->assertEquals([['id' => 2, 'name' => 'baz'], ['id' => 2, 'name' => 'bar'], ['id' => 1, 'name' => 'foo']], array_values($data->all()));
})->with('collections');

test('sortByAlwaysReturnsAssoc', function (string $collection) {
    $data = new $collection(['a' => 'taylor', 'b' => 'dayle']);
    $data = $data->sortBy(function ($x) {
        return $x;
    });

    $this->assertEquals(['b' => 'dayle', 'a' => 'taylor'], $data->all());

    $data = new $collection(['taylor', 'dayle']);
    $data = $data->sortBy(function ($x) {
        return $x;
    });

    $this->assertEquals([1 => 'dayle', 0 => 'taylor'], $data->all());

    $data = new $collection(['a' => ['sort' => 2], 'b' => ['sort' => 1]]);
    $data = $data->sortBy([['sort', 'asc']]);

    $this->assertEquals(['b' => ['sort' => 1], 'a' => ['sort' => 2]], $data->all());

    $data = new $collection([['sort' => 2], ['sort' => 1]]);
    $data = $data->sortBy([['sort', 'asc']]);

    $this->assertEquals([1 => ['sort' => 1], 0 => ['sort' => 2]], $data->all());
})->with('collections');

test('sortByMany', function (string $collection) {
    $defaultLocale = setlocale(LC_ALL, 0);

    $data = new $collection([['item' => '1'], ['item' => '10'], ['item' => 5], ['item' => 20]]);
    $expected = $data->pluck('item')->toArray();

    sort($expected);
    $data = $data->sortBy(['item']);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    rsort($expected);
    $data = $data->sortBy([['item', 'desc']]);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    sort($expected, SORT_STRING);
    $data = $data->sortBy(['item'], SORT_STRING);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    rsort($expected, SORT_STRING);
    $data = $data->sortBy([['item', 'desc']], SORT_STRING);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    sort($expected, SORT_NUMERIC);
    $data = $data->sortBy(['item'], SORT_NUMERIC);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    rsort($expected, SORT_NUMERIC);
    $data = $data->sortBy([['item', 'desc']], SORT_NUMERIC);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    $data = new $collection([['item' => 'img1'], ['item' => 'img101'], ['item' => 'img10'], ['item' => 'img11']]);
    $expected = $data->pluck('item')->toArray();

    sort($expected, SORT_NUMERIC);
    $data = $data->sortBy(['item'], SORT_NUMERIC);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    sort($expected);
    $data = $data->sortBy(['item']);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    sort($expected, SORT_NATURAL);
    $data = $data->sortBy(['item'], SORT_NATURAL);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    $data = new $collection([['item' => 'img1'], ['item' => 'Img101'], ['item' => 'img10'], ['item' => 'Img11']]);
    $expected = $data->pluck('item')->toArray();

    sort($expected);
    $data = $data->sortBy(['item']);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    sort($expected, SORT_NATURAL | SORT_FLAG_CASE);
    $data = $data->sortBy(['item'], SORT_NATURAL | SORT_FLAG_CASE);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    sort($expected, SORT_FLAG_CASE | SORT_STRING);
    $data = $data->sortBy(['item'], SORT_FLAG_CASE | SORT_STRING);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    sort($expected, SORT_FLAG_CASE | SORT_NUMERIC);
    $data = $data->sortBy(['item'], SORT_FLAG_CASE | SORT_NUMERIC);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    $data = new $collection([['item' => 'Österreich'], ['item' => 'Oesterreich'], ['item' => 'Zeta']]);
    $expected = $data->pluck('item')->toArray();

    sort($expected);
    $data = $data->sortBy(['item']);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    sort($expected, SORT_LOCALE_STRING);
    $data = $data->sortBy(['item'], SORT_LOCALE_STRING);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    setlocale(LC_ALL, 'de_DE');

    sort($expected, SORT_LOCALE_STRING);
    $data = $data->sortBy(['item'], SORT_LOCALE_STRING);
    $this->assertEquals($data->pluck('item')->toArray(), $expected);

    setlocale(LC_ALL, $defaultLocale);
})->with('collections');

test('naturalSortByManyWithNull', function (string $collection) {
    $itemFoo = new stdClass();
    $itemFoo->first = 'f';
    $itemFoo->second = null;
    $itemBar = new stdClass();
    $itemBar->first = 'f';
    $itemBar->second = 's';

    $data = new $collection([$itemFoo, $itemBar]);
    $data = $data->sortBy([
        ['first', 'desc'],
        ['second', 'desc'],
    ], SORT_NATURAL);

    $this->assertEquals($itemBar, $data->first());
    $this->assertEquals($itemFoo, $data->skip(1)->first());
})->with('collections');

test('sortKeys', function (string $collection) {
    $data = new $collection(['b' => 'dayle', 'a' => 'taylor']);

    $this->assertSame(['a' => 'taylor', 'b' => 'dayle'], $data->sortKeys()->all());
})->with('collections');

test('sortKeysDesc', function (string $collection) {
    $data = new $collection(['a' => 'taylor', 'b' => 'dayle']);

    $this->assertSame(['b' => 'dayle', 'a' => 'taylor'], $data->sortKeysDesc()->all());
})->with('collections');

test('sortKeysUsing', function (string $collection) {
    $data = new $collection(['B' => 'dayle', 'a' => 'taylor']);

    $this->assertSame(['a' => 'taylor', 'B' => 'dayle'], $data->sortKeysUsing('strnatcasecmp')->all());
})->with('collections');

test('reverse', function (string $collection) {
    $data = new $collection(['zaeed', 'alan']);
    $reversed = $data->reverse();

    $this->assertSame([1 => 'alan', 0 => 'zaeed'], $reversed->all());

    $data = new $collection(['name' => 'taylor', 'framework' => 'laravel']);
    $reversed = $data->reverse();

    $this->assertSame(['framework' => 'laravel', 'name' => 'taylor'], $reversed->all());
})->with('collections');

test('flip', function (string $collection) {
    $data = new $collection(['name' => 'taylor', 'framework' => 'laravel']);
    $this->assertEquals(['taylor' => 'name', 'laravel' => 'framework'], $data->flip()->toArray());
})->with('collections');

test('chunk', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    $data = $data->chunk(3);

    $this->assertInstanceOf($collection, $data);
    $this->assertInstanceOf($collection, $data->first());
    $this->assertCount(4, $data);
    $this->assertEquals([1, 2, 3], $data->first()->toArray());
    $this->assertEquals([9 => 10], $data->get(3)->toArray());
})->with('collections');

test('chunkWhenGivenZeroAsSize', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

    $this->assertEquals(
        [],
        $data->chunk(0)->toArray()
    );
})->with('collections');

test('chunkWhenGivenLessThanZero', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

    $this->assertEquals(
        [],
        $data->chunk(-1)->toArray()
    );
})->with('collections');

test('chunkPreservingKeys', function (string $collection) {
    $data = new $collection(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5]);

    $this->assertEquals(
        [['a' => 1, 'b' => 2], ['c' => 3, 'd' => 4], ['e' => 5]],
        $data->chunk(2)->toArray()
    );

    $data = new $collection([1, 2, 3, 4, 5]);

    $this->assertEquals(
        [[0 => 1, 1 => 2], [0 => 3, 1 => 4], [0 => 5]],
        $data->chunk(2, false)->toArray()
    );
})->with('collections');

test('splitIn', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    $data = $data->splitIn(3);

    $this->assertInstanceOf($collection, $data);
    $this->assertInstanceOf($collection, $data->first());
    $this->assertCount(3, $data);
    $this->assertEquals([1, 2, 3, 4], $data->get(0)->values()->toArray());
    $this->assertEquals([5, 6, 7, 8], $data->get(1)->values()->toArray());
    $this->assertEquals([9, 10], $data->get(2)->values()->toArray());
})->with('collections');

test('chunkWhileOnEqualElements', function (string $collection) {
    $data = (new $collection(['A', 'A', 'B', 'B', 'C', 'C', 'C']))
        ->chunkWhile(function ($current, $key, $chunk) {
            return $chunk->last() === $current;
        });

    $this->assertInstanceOf($collection, $data);
    $this->assertInstanceOf($collection, $data->first());
    $this->assertEquals([0 => 'A', 1 => 'A'], $data->first()->toArray());
    $this->assertEquals([2 => 'B', 3 => 'B'], $data->get(1)->toArray());
    $this->assertEquals([4 => 'C', 5 => 'C', 6 => 'C'], $data->last()->toArray());
})->with('collections');

test('chunkWhileOnContiguouslyIncreasingIntegers', function (string $collection) {
    $data = (new $collection([1, 4, 9, 10, 11, 12, 15, 16, 19, 20, 21]))
        ->chunkWhile(function ($current, $key, $chunk) {
            return $chunk->last() + 1 == $current;
        });

    $this->assertInstanceOf($collection, $data);
    $this->assertInstanceOf($collection, $data->first());
    $this->assertEquals([0 => 1], $data->first()->toArray());
    $this->assertEquals([1 => 4], $data->get(1)->toArray());
    $this->assertEquals([2 => 9, 3 => 10, 4 => 11, 5 => 12], $data->get(2)->toArray());
    $this->assertEquals([6 => 15, 7 => 16], $data->get(3)->toArray());
    $this->assertEquals([8 => 19, 9 => 20, 10 => 21], $data->last()->toArray());
})->with('collections');

test('chunkWhilePreservingStringKeys', function (string $collection) {
    $data = (new $collection(['a' => 1, 'b' => 1, 'c' => 2, 'd' => 2, 'e' => 3, 'f' => 3, 'g' => 3]))
        ->chunkWhile(function ($current, $key, $chunk) {
            return $chunk->last() === $current;
        });

    $this->assertInstanceOf($collection, $data);
    $this->assertInstanceOf($collection, $data->first());
    $this->assertEquals(['a' => 1, 'b' => 1], $data->first()->toArray());
    $this->assertEquals(['c' => 2, 'd' => 2], $data->get(1)->toArray());
    $this->assertEquals(['e' => 3, 'f' => 3, 'g' => 3], $data->last()->toArray());
})->with('collections');
