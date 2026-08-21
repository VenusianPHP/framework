<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (contains
 * family, some, sum, valueRetriever dot notation, pull, reject, search,
 * before/after).
 */

use Voyager\NutsAndBolts\Collection;

test('contains', function (string $collection) {
    $c = new $collection([1, 3, 5]);

    $this->assertTrue($c->contains(1));
    $this->assertTrue($c->contains('1'));
    $this->assertFalse($c->contains(2));
    $this->assertFalse($c->contains('2'));

    $c = new $collection(['1']);
    $this->assertTrue($c->contains('1'));
    $this->assertTrue($c->contains(1));

    $c = new $collection([null]);
    $this->assertTrue($c->contains(false));
    $this->assertTrue($c->contains(null));
    $this->assertTrue($c->contains([]));
    $this->assertTrue($c->contains(0));
    $this->assertTrue($c->contains(''));

    $c = new $collection([0]);
    $this->assertTrue($c->contains(0));
    $this->assertTrue($c->contains('0'));
    $this->assertTrue($c->contains(false));
    $this->assertTrue($c->contains(null));

    $this->assertTrue($c->contains(function ($value) {
        return $value < 5;
    }));
    $this->assertFalse($c->contains(function ($value) {
        return $value > 5;
    }));

    $c = new $collection([['v' => 1], ['v' => 3], ['v' => 5]]);

    $this->assertTrue($c->contains('v', 1));
    $this->assertFalse($c->contains('v', 2));

    $c = new $collection(['date', 'class', (object) ['foo' => 50]]);

    $this->assertTrue($c->contains('date'));
    $this->assertTrue($c->contains('class'));
    $this->assertFalse($c->contains('foo'));

    $c = new $collection([['a' => false, 'b' => false], ['a' => true, 'b' => false]]);

    $this->assertTrue($c->contains->a);
    $this->assertFalse($c->contains->b);

    $c = new $collection([
        null, 1, 2,
    ]);

    $this->assertTrue($c->contains(function ($value) {
        return is_null($value);
    }));
})->with('collections');

test('doesntContain', function (string $collection) {
    $c = new $collection([1, 3, 5]);

    $this->assertFalse($c->doesntContain(1));
    $this->assertFalse($c->doesntContain('1'));
    $this->assertTrue($c->doesntContain(2));
    $this->assertTrue($c->doesntContain('2'));

    $c = new $collection(['1']);
    $this->assertFalse($c->doesntContain('1'));
    $this->assertFalse($c->doesntContain(1));

    $c = new $collection([null]);
    $this->assertFalse($c->doesntContain(false));
    $this->assertFalse($c->doesntContain(null));
    $this->assertFalse($c->doesntContain([]));
    $this->assertFalse($c->doesntContain(0));
    $this->assertFalse($c->doesntContain(''));

    $c = new $collection([0]);
    $this->assertFalse($c->doesntContain(0));
    $this->assertFalse($c->doesntContain('0'));
    $this->assertFalse($c->doesntContain(false));
    $this->assertFalse($c->doesntContain(null));

    $this->assertFalse($c->doesntContain(function ($value) {
        return $value < 5;
    }));
    $this->assertTrue($c->doesntContain(function ($value) {
        return $value > 5;
    }));

    $c = new $collection([['v' => 1], ['v' => 3], ['v' => 5]]);

    $this->assertFalse($c->doesntContain('v', 1));
    $this->assertTrue($c->doesntContain('v', 2));

    $c = new $collection(['date', 'class', (object) ['foo' => 50]]);

    $this->assertFalse($c->doesntContain('date'));
    $this->assertFalse($c->doesntContain('class'));
    $this->assertTrue($c->doesntContain('foo'));

    $c = new $collection([['a' => false, 'b' => false], ['a' => true, 'b' => false]]);

    $this->assertFalse($c->doesntContain->a);
    $this->assertTrue($c->doesntContain->b);

    $c = new $collection([
        null, 1, 2,
    ]);

    $this->assertFalse($c->doesntContain(function ($value) {
        return is_null($value);
    }));
})->with('collections');

test('doesntContainStrict', function (string $collection) {
    $c = new $collection([1, 3, 5, '02']);

    $this->assertFalse($c->doesntContainStrict(1));
    $this->assertTrue($c->doesntContainStrict('1'));
    $this->assertTrue($c->doesntContainStrict(2));
    $this->assertFalse($c->doesntContainStrict('02'));
    $this->assertTrue($c->doesntContainStrict('2'));
    $this->assertTrue($c->doesntContainStrict(true));
    $this->assertFalse($c->doesntContainStrict(function ($value) {
        return $value < 5;
    }));
    $this->assertTrue($c->doesntContainStrict(function ($value) {
        return $value > 5;
    }));

    $c = new $collection([0]);
    $this->assertFalse($c->doesntContainStrict(0));
    $this->assertTrue($c->doesntContainStrict('0'));

    $this->assertTrue($c->doesntContainStrict(false));
    $this->assertTrue($c->doesntContainStrict(null));

    $c = new $collection([1, null]);
    $this->assertFalse($c->doesntContainStrict(null));
    $this->assertTrue($c->doesntContainStrict(0));
    $this->assertTrue($c->doesntContainStrict(false));

    $c = new $collection([['v' => 1], ['v' => 3], ['v' => '04'], ['v' => 5]]);

    $this->assertFalse($c->doesntContainStrict('v', 1));
    $this->assertTrue($c->doesntContainStrict('v', 2));
    $this->assertTrue($c->doesntContainStrict('v', '1'));
    $this->assertTrue($c->doesntContainStrict('v', 4));
    $this->assertFalse($c->doesntContainStrict('v', '04'));
    $this->assertTrue($c->doesntContainStrict('v', '4'));

    $c = new $collection(['date', 'class', (object) ['foo' => 50], '']);

    $this->assertFalse($c->doesntContainStrict('date'));
    $this->assertFalse($c->doesntContainStrict('class'));
    $this->assertTrue($c->doesntContainStrict('foo'));
    $this->assertTrue($c->doesntContainStrict(null));
    $this->assertFalse($c->doesntContainStrict(''));
})->with('collections');

test('some', function (string $collection) {
    $c = new $collection([1, 3, 5]);

    $this->assertTrue($c->some(1));
    $this->assertFalse($c->some(2));
    $this->assertTrue($c->some(function ($value) {
        return $value < 5;
    }));
    $this->assertFalse($c->some(function ($value) {
        return $value > 5;
    }));

    $c = new $collection([['v' => 1], ['v' => 3], ['v' => 5]]);

    $this->assertTrue($c->some('v', 1));
    $this->assertFalse($c->some('v', 2));

    $c = new $collection(['date', 'class', (object) ['foo' => 50]]);

    $this->assertTrue($c->some('date'));
    $this->assertTrue($c->some('class'));
    $this->assertFalse($c->some('foo'));

    $c = new $collection([['a' => false, 'b' => false], ['a' => true, 'b' => false]]);

    $this->assertTrue($c->some->a);
    $this->assertFalse($c->some->b);

    $c = new $collection([
        null, 1, 2,
    ]);

    $this->assertTrue($c->some(function ($value) {
        return is_null($value);
    }));
})->with('collections');

test('containsStrict', function (string $collection) {
    $c = new $collection([1, 3, 5, '02']);

    $this->assertTrue($c->containsStrict(1));
    $this->assertFalse($c->containsStrict('1'));
    $this->assertFalse($c->containsStrict(2));
    $this->assertTrue($c->containsStrict('02'));
    $this->assertFalse($c->containsStrict(true));
    $this->assertTrue($c->containsStrict(function ($value) {
        return $value < 5;
    }));
    $this->assertFalse($c->containsStrict(function ($value) {
        return $value > 5;
    }));

    $c = new $collection([0]);
    $this->assertTrue($c->containsStrict(0));
    $this->assertFalse($c->containsStrict('0'));

    $this->assertFalse($c->containsStrict(false));
    $this->assertFalse($c->containsStrict(null));

    $c = new $collection([1, null]);
    $this->assertTrue($c->containsStrict(null));
    $this->assertFalse($c->containsStrict(0));
    $this->assertFalse($c->containsStrict(false));

    $c = new $collection([['v' => 1], ['v' => 3], ['v' => '04'], ['v' => 5]]);

    $this->assertTrue($c->containsStrict('v', 1));
    $this->assertFalse($c->containsStrict('v', 2));
    $this->assertFalse($c->containsStrict('v', '1'));
    $this->assertFalse($c->containsStrict('v', 4));
    $this->assertTrue($c->containsStrict('v', '04'));

    $c = new $collection(['date', 'class', (object) ['foo' => 50], '']);

    $this->assertTrue($c->containsStrict('date'));
    $this->assertTrue($c->containsStrict('class'));
    $this->assertFalse($c->containsStrict('foo'));
    $this->assertFalse($c->containsStrict(null));
    $this->assertTrue($c->containsStrict(''));
})->with('collections');

test('containsWithOperator', function (string $collection) {
    $c = new $collection([['v' => 1], ['v' => 3], ['v' => '4'], ['v' => 5]]);

    $this->assertTrue($c->contains('v', '=', 4));
    $this->assertTrue($c->contains('v', '==', 4));
    $this->assertFalse($c->contains('v', '===', 4));
    $this->assertTrue($c->contains('v', '>', 4));
})->with('collections');

test('gettingSumFromCollection', function (string $collection) {
    $c = new $collection([(object) ['foo' => 50], (object) ['foo' => 50]]);
    $this->assertEquals(100, $c->sum('foo'));

    $c = new $collection([(object) ['foo' => 50], (object) ['foo' => 50]]);
    $this->assertEquals(100, $c->sum(function ($i) {
        return $i->foo;
    }));
})->with('collections');

test('canSumValuesWithoutACallback', function (string $collection) {
    $c = new $collection([1, 2, 3, 4, 5]);
    $this->assertEquals(15, $c->sum());
})->with('collections');

test('gettingSumFromEmptyCollection', function (string $collection) {
    $c = new $collection;
    $this->assertEquals(0, $c->sum('foo'));
})->with('collections');

test('valueRetrieverAcceptsDotNotation', function (string $collection) {
    $c = new $collection([
        (object) ['id' => 1, 'foo' => ['bar' => 'B']], (object) ['id' => 2, 'foo' => ['bar' => 'A']],
    ]);

    $c = $c->sortBy('foo.bar');
    $this->assertEquals([2, 1], $c->pluck('id')->all());
})->with('collections');

test('pullRetrievesItemFromCollection', function () {
    $c = new Collection(['foo', 'bar']);

    $this->assertSame('foo', $c->pull(0));
    $this->assertSame('bar', $c->pull(1));

    $c = new Collection(['foo', 'bar']);

    $this->assertNull($c->pull(-1));
    $this->assertNull($c->pull(2));
});

test('pullRemovesItemFromCollection', function () {
    $c = new Collection(['foo', 'bar']);
    $c->pull(0);
    $this->assertEquals([1 => 'bar'], $c->all());
    $c->pull(1);
    $this->assertEquals([], $c->all());
});

test('pullRemovesItemFromNestedCollection', function () {
    $nestedCollection = new Collection([
        new Collection([
            'value',
            new Collection([
                'bar' => 'baz',
                'test' => 'value',
            ]),
        ]),
        'bar',
    ]);

    $nestedCollection->pull('0.1.test');

    $actualArray = $nestedCollection->toArray();
    $expectedArray = [
        [
            'value',
            ['bar' => 'baz'],
        ],
        'bar',
    ];

    $this->assertEquals($expectedArray, $actualArray);
});

test('pullReturnsDefault', function () {
    $c = new Collection([]);
    $value = $c->pull(0, 'foo');
    $this->assertSame('foo', $value);
});

test('rejectRemovesElementsPassingTruthTest', function (string $collection) {
    $c = new $collection(['foo', 'bar']);
    $this->assertEquals(['foo'], $c->reject('bar')->values()->all());

    $c = new $collection(['foo', 'bar']);
    $this->assertEquals(['foo'], $c->reject(function ($v) {
        return $v === 'bar';
    })->values()->all());

    $c = new $collection(['foo', null]);
    $this->assertEquals(['foo'], $c->reject(null)->values()->all());

    $c = new $collection(['foo', 'bar']);
    $this->assertEquals(['foo', 'bar'], $c->reject('baz')->values()->all());

    $c = new $collection(['foo', 'bar']);
    $this->assertEquals(['foo', 'bar'], $c->reject(function ($v) {
        return $v === 'baz';
    })->values()->all());

    $c = new $collection(['id' => 1, 'primary' => 'foo', 'secondary' => 'bar']);
    $this->assertEquals(['primary' => 'foo', 'secondary' => 'bar'], $c->reject(function ($item, $key) {
        return $key === 'id';
    })->all());
})->with('collections');

test('rejectWithoutAnArgumentRemovesTruthyValues', function (string $collection) {
    $data1 = new $collection([
        false,
        true,
        new $collection(),
        0,
    ]);
    $this->assertSame([0 => false, 3 => 0], $data1->reject()->all());

    $data2 = new $collection([
        'a' => true,
        'b' => true,
        'c' => true,
    ]);
    $this->assertTrue(
        $data2->reject()->isEmpty()
    );
})->with('collections');

test('searchReturnsIndexOfFirstFoundItem', function (string $collection) {
    $c = new $collection([1, 2, 3, 4, 5, 2, 5, 'foo' => 'bar']);

    $this->assertEquals(1, $c->search(2));
    $this->assertEquals(1, $c->search('2'));
    $this->assertSame('foo', $c->search('bar'));
    $this->assertEquals(4, $c->search(function ($value) {
        return $value > 4;
    }));
    $this->assertSame('foo', $c->search(function ($value) {
        return ! is_numeric($value);
    }));
})->with('collections');

test('searchInStrictMode', function (string $collection) {
    $c = new $collection([false, 0, 1, [], '']);
    $this->assertFalse($c->search('false', true));
    $this->assertFalse($c->search('1', true));
    $this->assertEquals(0, $c->search(false, true));
    $this->assertEquals(1, $c->search(0, true));
    $this->assertEquals(2, $c->search(1, true));
    $this->assertEquals(3, $c->search([], true));
    $this->assertEquals(4, $c->search('', true));
})->with('collections');

test('searchReturnsFalseWhenItemIsNotFound', function (string $collection) {
    $c = new $collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

    $this->assertFalse($c->search(6));
    $this->assertFalse($c->search('foo'));
    $this->assertFalse($c->search(function ($value) {
        return $value < 1 && is_numeric($value);
    }));
    $this->assertFalse($c->search(function ($value) {
        return $value === 'nope';
    }));
})->with('collections');

test('beforeReturnsItemBeforeTheGivenItem', function (string $collection) {
    $c = new $collection([1, 2, 3, 4, 5, 2, 5, 'name' => 'taylor', 'framework' => 'laravel']);

    $this->assertEquals(1, $c->before(2));
    $this->assertEquals(1, $c->before('2'));
    $this->assertEquals(5, $c->before('taylor'));
    $this->assertSame('taylor', $c->before('laravel'));
    $this->assertEquals(4, $c->before(function ($value) {
        return $value > 4;
    }));
    $this->assertEquals(5, $c->before(function ($value) {
        return ! is_numeric($value);
    }));
})->with('collections');

test('beforeInStrictMode', function (string $collection) {
    $c = new $collection([false, 0, 1, [], '']);
    $this->assertNull($c->before('false', true));
    $this->assertNull($c->before('1', true));
    $this->assertNull($c->before(false, true));
    $this->assertEquals(false, $c->before(0, true));
    $this->assertEquals(0, $c->before(1, true));
    $this->assertEquals(1, $c->before([], true));
    $this->assertEquals([], $c->before('', true));
})->with('collections');

test('beforeReturnsNullWhenItemIsNotFound', function (string $collection) {
    $c = new $collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

    $this->assertNull($c->before(6));
    $this->assertNull($c->before('foo'));
    $this->assertNull($c->before(function ($value) {
        return $value < 1 && is_numeric($value);
    }));
    $this->assertNull($c->before(function ($value) {
        return $value === 'nope';
    }));
})->with('collections');

test('beforeReturnsNullWhenItemOnTheFirstitem', function (string $collection) {
    $c = new $collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

    $this->assertNull($c->before(1));
    $this->assertNull($c->before(function ($value) {
        return $value < 2 && is_numeric($value);
    }));

    $c = new $collection(['foo' => 'bar', 1, 2, 3, 4, 5]);
    $this->assertNull($c->before('bar'));
})->with('collections');

test('afterReturnsItemAfterTheGivenItem', function (string $collection) {
    $c = new $collection([1, 2, 3, 4, 2, 5, 'name' => 'taylor', 'framework' => 'laravel']);

    $this->assertEquals(2, $c->after(1));
    $this->assertEquals(3, $c->after(2));
    $this->assertEquals(4, $c->after(3));
    $this->assertEquals(2, $c->after(4));
    $this->assertEquals('taylor', $c->after(5));
    $this->assertEquals('laravel', $c->after('taylor'));

    $this->assertEquals(4, $c->after(function ($value) {
        return $value > 2;
    }));
    $this->assertEquals('laravel', $c->after(function ($value) {
        return ! is_numeric($value);
    }));
})->with('collections');

test('afterInStrictMode', function (string $collection) {
    $c = new $collection([false, 0, 1, [], '']);

    $this->assertNull($c->after('false', true));
    $this->assertNull($c->after('1', true));
    $this->assertNull($c->after('', true));
    $this->assertEquals(0, $c->after(false, true));
    $this->assertEquals([], $c->after(1, true));
    $this->assertEquals('', $c->after([], true));
})->with('collections');

test('afterReturnsNullWhenItemIsNotFound', function (string $collection) {
    $c = new $collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

    $this->assertNull($c->after(6));
    $this->assertNull($c->after('foo'));
    $this->assertNull($c->after(function ($value) {
        return $value < 1 && is_numeric($value);
    }));
    $this->assertNull($c->after(function ($value) {
        return $value === 'nope';
    }));
})->with('collections');

test('afterReturnsNullWhenItemOnTheLastItem', function (string $collection) {
    $c = new $collection([1, 2, 3, 4, 5, 'foo' => 'bar']);

    $this->assertNull($c->after('bar'));
    $this->assertNull($c->after(function ($value) {
        return $value > 4 && ! is_numeric($value);
    }));

    $c = new $collection(['foo' => 'bar', 1, 2, 3, 4, 5]);
    $this->assertNull($c->after(5));
})->with('collections');
