<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (partition,
 * tap, when/unless family and their higher-order proxies, has/put/get,
 * whereNull/whereNotNull, collect, undot/dot, ensure, percentage).
 */

use Voyager\NutsAndBolts\Collection;

test('partition', function (string $collection) {
    $data = new $collection(range(1, 10));

    [$firstPartition, $secondPartition] = $data->partition(function ($i) {
        return $i <= 5;
    })->all();

    $this->assertEquals([1, 2, 3, 4, 5], $firstPartition->values()->toArray());
    $this->assertEquals([6, 7, 8, 9, 10], $secondPartition->values()->toArray());
})->with('collections');

test('partitionCallbackWithKey', function (string $collection) {
    $data = new $collection(['zero', 'one', 'two', 'three']);

    [$even, $odd] = $data->partition(function ($item, $index) {
        return $index % 2 === 0;
    })->all();

    $this->assertEquals(['zero', 'two'], $even->values()->toArray());
    $this->assertEquals(['one', 'three'], $odd->values()->toArray());
})->with('collections');

test('partitionByKey', function (string $collection) {
    $courses = new $collection([
        ['free' => true, 'title' => 'Basic'], ['free' => false, 'title' => 'Premium'],
    ]);

    [$free, $premium] = $courses->partition('free')->all();

    $this->assertSame([['free' => true, 'title' => 'Basic']], $free->values()->toArray());
    $this->assertSame([['free' => false, 'title' => 'Premium']], $premium->values()->toArray());
})->with('collections');

test('partitionWithOperators', function (string $collection) {
    $data = new $collection([
        ['name' => 'Tim', 'age' => 17],
        ['name' => 'Agatha', 'age' => 62],
        ['name' => 'Kristina', 'age' => 33],
        ['name' => 'Tim', 'age' => 41],
    ]);

    [$tims, $others] = $data->partition('name', 'Tim')->all();

    $this->assertEquals([
        ['name' => 'Tim', 'age' => 17],
        ['name' => 'Tim', 'age' => 41],
    ], $tims->values()->all());

    $this->assertEquals([
        ['name' => 'Agatha', 'age' => 62],
        ['name' => 'Kristina', 'age' => 33],
    ], $others->values()->all());

    [$adults, $minors] = $data->partition('age', '>=', 18)->all();

    $this->assertEquals([
        ['name' => 'Agatha', 'age' => 62],
        ['name' => 'Kristina', 'age' => 33],
        ['name' => 'Tim', 'age' => 41],
    ], $adults->values()->all());

    $this->assertEquals([
        ['name' => 'Tim', 'age' => 17],
    ], $minors->values()->all());
})->with('collections');

test('partitionPreservesKeys', function (string $collection) {
    $courses = new $collection([
        'a' => ['free' => true], 'b' => ['free' => false], 'c' => ['free' => true],
    ]);

    [$free, $premium] = $courses->partition('free')->all();

    $this->assertSame(['a' => ['free' => true], 'c' => ['free' => true]], $free->toArray());
    $this->assertSame(['b' => ['free' => false]], $premium->toArray());
})->with('collections');

test('partitionEmptyCollection', function (string $collection) {
    $data = new $collection;

    $this->assertCount(2, $data->partition(function () {
        return true;
    }));
})->with('collections');

test('higherOrderPartition', function (string $collection) {
    $courses = new $collection([
        'a' => ['free' => true], 'b' => ['free' => false], 'c' => ['free' => true],
    ]);

    [$free, $premium] = $courses->partition->free->all();

    $this->assertSame(['a' => ['free' => true], 'c' => ['free' => true]], $free->toArray());

    $this->assertSame(['b' => ['free' => false]], $premium->toArray());
})->with('collections');

test('tap', function (string $collection) {
    $data = new $collection([1, 2, 3]);

    $fromTap = [];
    $tappedInstance = null;
    $data = $data->tap(function ($data) use (&$fromTap, &$tappedInstance) {
        $fromTap = $data->slice(0, 1)->toArray();
        $tappedInstance = $data;
    });

    $this->assertSame($data, $tappedInstance);
    $this->assertSame([1], $fromTap);
    $this->assertSame([1, 2, 3], $data->toArray());
})->with('collections');

test('when', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->when('adam', function ($data, $newName) {
        return $data->concat([$newName]);
    });

    $this->assertSame(['michael', 'tom', 'adam'], $data->toArray());

    $data = new $collection(['michael', 'tom']);

    $data = $data->when(false, function ($data) {
        return $data->concat(['adam']);
    });

    $this->assertSame(['michael', 'tom'], $data->toArray());
})->with('collections');

test('whenDefault', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->when(false, function ($data) {
        return $data->concat(['adam']);
    }, function ($data) {
        return $data->concat(['taylor']);
    });

    $this->assertSame(['michael', 'tom', 'taylor'], $data->toArray());
})->with('collections');

test('whenEmpty', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->whenEmpty(function () {
        throw new Exception('whenEmpty() should not trigger on a collection with items');
    });

    $this->assertSame(['michael', 'tom'], $data->toArray());

    $data = new $collection;

    $data = $data->whenEmpty(function ($data) {
        return $data->concat(['adam']);
    });

    $this->assertSame(['adam'], $data->toArray());
})->with('collections');

test('whenEmptyDefault', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->whenEmpty(function ($data) {
        return $data->concat(['adam']);
    }, function ($data) {
        return $data->concat(['taylor']);
    });

    $this->assertSame(['michael', 'tom', 'taylor'], $data->toArray());
})->with('collections');

test('whenNotEmpty', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->whenNotEmpty(function ($data) {
        return $data->concat(['adam']);
    });

    $this->assertSame(['michael', 'tom', 'adam'], $data->toArray());

    $data = new $collection;

    $data = $data->whenNotEmpty(function ($data) {
        return $data->concat(['adam']);
    });

    $this->assertSame([], $data->toArray());
})->with('collections');

test('whenNotEmptyDefault', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->whenNotEmpty(function ($data) {
        return $data->concat(['adam']);
    }, function ($data) {
        return $data->concat(['taylor']);
    });

    $this->assertSame(['michael', 'tom', 'adam'], $data->toArray());
})->with('collections');

test('whenNotEmpty returns the callbacks value even when it is not a collection', function (string $collection) {
    // Regression: package:discover chains ->whenNotEmpty(fn () => $this->newLine()),
    // whose callback returns the command instance. A strict ": static" return type
    // TypeError'd on that. whenNotEmpty must pass the callback's value straight through.
    $data = new $collection(['michael', 'tom']);

    $sentinel = new stdClass;

    $result = $data->whenNotEmpty(fn () => $sentinel);

    $this->assertSame($sentinel, $result);
})->with('collections');

test('whenNotEmpty with a void callback falls back to the collection', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $result = $data->whenNotEmpty(function () {
        // no return value
    });

    $this->assertInstanceOf($collection, $result);
    $this->assertSame(['michael', 'tom'], $result->toArray());
})->with('collections');

test('whenEmpty returns the callbacks value even when it is not a collection', function (string $collection) {
    $data = new $collection;

    $sentinel = new stdClass;

    $result = $data->whenEmpty(fn () => $sentinel);

    $this->assertSame($sentinel, $result);
})->with('collections');

test('higherOrderWhenAndUnless', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->when(true)->concat(['chris']);

    $this->assertSame(['michael', 'tom', 'chris'], $data->toArray());

    $data = $data->when(false)->concat(['adam']);

    $this->assertSame(['michael', 'tom', 'chris'], $data->toArray());

    $data = $data->unless(false)->concat(['adam']);

    $this->assertSame(['michael', 'tom', 'chris', 'adam'], $data->toArray());

    $data = $data->unless(true)->concat(['bogdan']);

    $this->assertSame(['michael', 'tom', 'chris', 'adam'], $data->toArray());
})->with('collections');

test('higherOrderWhenAndUnlessWithProxy', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->when->contains('michael')->concat(['chris']);

    $this->assertSame(['michael', 'tom', 'chris'], $data->toArray());

    $data = $data->when->contains('missing')->concat(['adam']);

    $this->assertSame(['michael', 'tom', 'chris'], $data->toArray());

    $data = $data->unless->contains('missing')->concat(['adam']);

    $this->assertSame(['michael', 'tom', 'chris', 'adam'], $data->toArray());

    $data = $data->unless->contains('adam')->concat(['bogdan']);

    $this->assertSame(['michael', 'tom', 'chris', 'adam'], $data->toArray());
})->with('collections');

test('unless', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->unless(false, function ($data) {
        return $data->concat(['caleb']);
    });

    $this->assertSame(['michael', 'tom', 'caleb'], $data->toArray());

    $data = new $collection(['michael', 'tom']);

    $data = $data->unless(true, function ($data) {
        return $data->concat(['caleb']);
    });

    $this->assertSame(['michael', 'tom'], $data->toArray());
})->with('collections');

test('unlessDefault', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->unless(true, function ($data) {
        return $data->concat(['caleb']);
    }, function ($data) {
        return $data->concat(['taylor']);
    });

    $this->assertSame(['michael', 'tom', 'taylor'], $data->toArray());
})->with('collections');

test('unlessEmpty', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->unlessEmpty(function ($data) {
        return $data->concat(['adam']);
    });

    $this->assertSame(['michael', 'tom', 'adam'], $data->toArray());

    $data = new $collection;

    $data = $data->unlessEmpty(function ($data) {
        return $data->concat(['adam']);
    });

    $this->assertSame([], $data->toArray());
})->with('collections');

test('unlessEmptyDefault', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->unlessEmpty(function ($data) {
        return $data->concat(['adam']);
    }, function ($data) {
        return $data->concat(['taylor']);
    });

    $this->assertSame(['michael', 'tom', 'adam'], $data->toArray());
})->with('collections');

test('unlessNotEmpty', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->unlessNotEmpty(function ($data) {
        return $data->concat(['adam']);
    });

    $this->assertSame(['michael', 'tom'], $data->toArray());

    $data = new $collection;

    $data = $data->unlessNotEmpty(function ($data) {
        return $data->concat(['adam']);
    });

    $this->assertSame(['adam'], $data->toArray());
})->with('collections');

test('unlessNotEmptyDefault', function (string $collection) {
    $data = new $collection(['michael', 'tom']);

    $data = $data->unlessNotEmpty(function ($data) {
        return $data->concat(['adam']);
    }, function ($data) {
        return $data->concat(['taylor']);
    });

    $this->assertSame(['michael', 'tom', 'taylor'], $data->toArray());
})->with('collections');

test('hasReturnsValidResults', function (string $collection) {
    $data = new $collection(['foo' => 'one', 'bar' => 'two', 1 => 'three']);
    $this->assertTrue($data->has('foo'));
    $this->assertTrue($data->has('foo', 'bar', 1));
    $this->assertFalse($data->has('foo', 'bar', 1, 'baz'));
    $this->assertFalse($data->has('baz'));
})->with('collections');

test('putAddsItemToCollection', function () {
    $data = new Collection;
    $this->assertSame([], $data->toArray());
    $data->put('foo', 1);
    $this->assertSame(['foo' => 1], $data->toArray());
    $data->put('bar', ['nested' => 'two']);
    $this->assertSame(['foo' => 1, 'bar' => ['nested' => 'two']], $data->toArray());
    $data->put('foo', 3);
    $this->assertSame(['foo' => 3, 'bar' => ['nested' => 'two']], $data->toArray());
});

test('itThrowsExceptionWhenTryingToAccessNoProxyProperty', function (string $collection) {
    $data = new $collection;
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Property [foo] does not exist on this collection instance.');
    $data->foo;
})->with('collections');

test('getWithNullReturnsNull', function (string $collection) {
    $data = new $collection([1, 2, 3]);
    $this->assertNull($data->get(null));
})->with('collections');

test('getWithDefaultValue', function (string $collection) {
    $data = new $collection(['name' => 'taylor', 'framework' => 'laravel']);
    $this->assertEquals('34', $data->get('age', 34));
})->with('collections');

test('getWithCallbackAsDefaultValue', function (string $collection) {
    $data = new $collection(['name' => 'taylor', 'framework' => 'laravel']);
    $result = $data->get('email', function () {
        return 'taylor@example.com';
    });
    $this->assertEquals('taylor@example.com', $result);
})->with('collections');

test('whereNull', function (string $collection) {
    $data = new $collection([
        ['name' => 'Taylor'],
        ['name' => null],
        ['name' => 'Bert'],
        ['name' => false],
        ['name' => ''],
    ]);

    $this->assertSame([
        1 => ['name' => null],
    ], $data->whereNull('name')->all());

    $this->assertSame([], $data->whereNull()->all());
})->with('collections');

test('whereNullWithoutKey', function (string $collection) {
    $collection = new $collection([1, null, 3, 'null', false, true]);
    $this->assertSame([
        1 => null,
    ], $collection->whereNull()->all());
})->with('collections');

test('whereNotNull', function (string $collection) {
    $data = new $collection($originalData = [
        ['name' => 'Taylor'],
        ['name' => null],
        ['name' => 'Bert'],
        ['name' => false],
        ['name' => ''],
    ]);

    $this->assertSame([
        0 => ['name' => 'Taylor'],
        2 => ['name' => 'Bert'],
        3 => ['name' => false],
        4 => ['name' => ''],
    ], $data->whereNotNull('name')->all());

    $this->assertSame($originalData, $data->whereNotNull()->all());
})->with('collections');

test('whereNotNullWithoutKey', function (string $collection) {
    $data = new $collection([1, null, 3, 'null', false, true]);

    $this->assertSame([
        0 => 1,
        2 => 3,
        3 => 'null',
        4 => false,
        5 => true,
    ], $data->whereNotNull()->all());
})->with('collections');

test('collect', function (string $collection) {
    $data = $collection::make([
        'a' => 1,
        'b' => 2,
        'c' => 3,
    ])->collect();

    $this->assertInstanceOf(Collection::class, $data);

    $this->assertSame([
        'a' => 1,
        'b' => 2,
        'c' => 3,
    ], $data->all());
})->with('collections');

test('undot', function (string $collection) {
    $data = $collection::make([
        'name' => 'Taylor',
        'meta.foo' => 'bar',
        'meta.baz' => 'boom',
        'meta.bam.boom' => 'bip',
    ])->undot();
    $this->assertSame([
        'name' => 'Taylor',
        'meta' => [
            'foo' => 'bar',
            'baz' => 'boom',
            'bam' => [
                'boom' => 'bip',
            ],
        ],
    ], $data->all());

    $data = $collection::make([
        'foo.0' => 'bar',
        'foo.1' => 'baz',
        'foo.baz' => 'boom',
    ])->undot();
    $this->assertSame([
        'foo' => [
            'bar',
            'baz',
            'baz' => 'boom',
        ],
    ], $data->all());
})->with('collections');

test('dot', function (string $collection) {
    $data = $collection::make([
        'name' => 'Taylor',
        'meta' => [
            'foo' => 'bar',
            'baz' => 'boom',
            'bam' => [
                'boom' => 'bip',
            ],
        ],
    ])->dot();
    $this->assertSame([
        'name' => 'Taylor',
        'meta.foo' => 'bar',
        'meta.baz' => 'boom',
        'meta.bam.boom' => 'bip',
    ], $data->all());

    $data = $collection::make([
        'foo' => [
            'bar',
            'baz',
            'baz' => 'boom',
        ],
    ])->dot();
    $this->assertSame([
        'foo.0' => 'bar',
        'foo.1' => 'baz',
        'foo.baz' => 'boom',
    ], $data->all());
})->with('collections');

test('dotWithDepth', function (string $collection) {
    $data = $collection::make([
        'name' => 'Taylor',
        'meta' => [
            'foo' => 'bar',
            'bam' => [
                'boom' => 'bip',
            ],
        ],
    ])->dot(1);
    $this->assertSame([
        'name' => 'Taylor',
        'meta.foo' => 'bar',
        'meta.bam' => [
            'boom' => 'bip',
        ],
    ], $data->all());
})->with('collections');

test('ensureForScalar', function (string $collection) {
    $data = $collection::make([1, 2, 3]);
    $data->ensure('int');

    $data = $collection::make([1, 2, 3, 'foo']);
    $this->expectException(UnexpectedValueException::class);
    $this->expectExceptionMessage("Collection should only include [int] items, but 'string' found at position 3.");
    $data->ensure('int');
})->with('collections');

test('ensureForObjects', function (string $collection) {
    $data = $collection::make([new stdClass, new stdClass, new stdClass]);
    $data->ensure(stdClass::class);

    $data = $collection::make([new stdClass, new stdClass, new stdClass, $collection]);
    $this->expectException(UnexpectedValueException::class);
    $this->expectExceptionMessage(sprintf('Collection should only include [%s] items, but \'%s\' found at position %d.', class_basename(new stdClass()), gettype($collection), 3));
    $data->ensure(stdClass::class);
})->with('collections');

test('ensureForInheritance', function (string $collection) {
    $data = $collection::make([new Error, new Error]);
    $data->ensure(Throwable::class);

    $wrongType = new $collection;
    $data = $collection::make([new Error, new Error, $wrongType]);
    $this->expectException(UnexpectedValueException::class);
    $this->expectExceptionMessage(sprintf("Collection should only include [%s] items, but '%s' found at position %d.", Throwable::class, get_class($wrongType), 2));
    $data->ensure(Throwable::class);
})->with('collections');

test('ensureForMultipleTypes', function (string $collection) {
    $data = $collection::make([new Error, 123]);
    $data->ensure([Throwable::class, 'int']);

    $wrongType = new $collection;
    $data = $collection::make([new Error, new Error, $wrongType]);
    $this->expectException(UnexpectedValueException::class);
    $this->expectExceptionMessage(sprintf('Collection should only include [%s] items, but \'%s\' found at position %d.', implode(', ', [Throwable::class, 'int']), get_class($wrongType), 2));
    $data->ensure([Throwable::class, 'int']);
})->with('collections');

test('percentageWithFlatCollection', function (string $collection) {
    $collection = new $collection([1, 1, 2, 2, 2, 3]);

    $this->assertSame(33.33, $collection->percentage(fn ($value) => $value === 1));
    $this->assertSame(50.00, $collection->percentage(fn ($value) => $value === 2));
    $this->assertSame(16.67, $collection->percentage(fn ($value) => $value === 3));
    $this->assertSame(0.0, $collection->percentage(fn ($value) => $value === 5));
})->with('collections');

test('percentageWithNestedCollection', function (string $collection) {
    $collection = new $collection([
        ['name' => 'Taylor', 'foo' => 'foo'],
        ['name' => 'Nuno', 'foo' => 'bar'],
        ['name' => 'Dries', 'foo' => 'bar'],
        ['name' => 'Jess', 'foo' => 'baz'],
    ]);

    $this->assertSame(25.00, $collection->percentage(fn ($value) => $value['foo'] === 'foo'));
    $this->assertSame(50.00, $collection->percentage(fn ($value) => $value['foo'] === 'bar'));
    $this->assertSame(25.00, $collection->percentage(fn ($value) => $value['foo'] === 'baz'));
    $this->assertSame(0.0, $collection->percentage(fn ($value) => $value['foo'] === 'test'));
})->with('collections');

test('highOrderPercentage', function (string $collection) {
    $collection = new $collection([
        ['name' => 'Taylor', 'active' => true],
        ['name' => 'Nuno', 'active' => true],
        ['name' => 'Dries', 'active' => false],
        ['name' => 'Jess', 'active' => true],
    ]);

    $this->assertSame(75.00, $collection->percentage->active);
})->with('collections');

test('percentageReturnsNullForEmptyCollections', function (string $collection) {
    $collection = new $collection([]);

    $this->assertNull($collection->percentage(fn ($value) => $value === 1));
})->with('collections');
