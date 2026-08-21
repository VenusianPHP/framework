<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (first/sole/
 * firstOrFail/last/pop/shift/sliding). Every case that upstream ran through
 * `collectionClassProvider` runs here through the shared 'collections'
 * dataset, so it still exercises both Collection and LazyCollection.
 */

use Tests\Collections\StaffEnum;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\Exceptions\ItemNotFoundException;
use Voyager\NutsAndBolts\Exceptions\MultipleItemsFoundException;

test('firstReturnsFirstItemInCollection', function (string $collection) {
    $c = new $collection(['foo', 'bar']);
    $this->assertSame('foo', $c->first());
})->with('collections');

test('firstWithCallback', function (string $collection) {
    $data = new $collection(['foo', 'bar', 'baz']);
    $result = $data->first(function ($value) {
        return $value === 'bar';
    });
    $this->assertSame('bar', $result);
})->with('collections');

test('firstWithCallbackAndDefault', function (string $collection) {
    $data = new $collection(['foo', 'bar']);
    $result = $data->first(function ($value) {
        return $value === 'baz';
    }, 'default');
    $this->assertSame('default', $result);
})->with('collections');

test('firstWithDefaultAndWithoutCallback', function (string $collection) {
    $data = new $collection;
    $result = $data->first(null, 'default');
    $this->assertSame('default', $result);

    $data = new $collection(['foo', 'bar']);
    $result = $data->first(null, 'default');
    $this->assertSame('foo', $result);
})->with('collections');

test('soleReturnsFirstItemInCollectionIfOnlyOneExists', function (string $collection) {
    $collection = new $collection([
        ['name' => 'foo'],
        ['name' => 'bar'],
    ]);

    $this->assertSame(['name' => 'foo'], $collection->where('name', 'foo')->sole());
    $this->assertSame(['name' => 'foo'], $collection->sole('name', '=', 'foo'));
    $this->assertSame(['name' => 'foo'], $collection->sole('name', 'foo'));
})->with('collections');

test('soleThrowsExceptionIfNoItemsExist', function (string $collection) {
    $this->expectException(ItemNotFoundException::class);

    $collection = new $collection([
        ['name' => 'foo'],
        ['name' => 'bar'],
    ]);

    $collection->where('name', 'INVALID')->sole();
})->with('collections');

test('soleThrowsExceptionIfMoreThanOneItemExists', function (string $collection) {
    $this->expectExceptionObject(new MultipleItemsFoundException(2));

    $collection = new $collection([
        ['name' => 'foo'],
        ['name' => 'foo'],
        ['name' => 'bar'],
    ]);

    $collection->where('name', 'foo')->sole();
})->with('collections');

test('soleReturnsFirstItemInCollectionIfOnlyOneExistsWithCallback', function (string $collection) {
    $data = new $collection(['foo', 'bar', 'baz']);
    $result = $data->sole(function ($value) {
        return $value === 'bar';
    });
    $this->assertSame('bar', $result);
})->with('collections');

test('soleThrowsExceptionIfNoItemsExistWithCallback', function (string $collection) {
    $this->expectException(ItemNotFoundException::class);

    $data = new $collection(['foo', 'bar', 'baz']);

    $data->sole(function ($value) {
        return $value === 'invalid';
    });
})->with('collections');

test('soleThrowsExceptionIfMoreThanOneItemExistsWithCallback', function (string $collection) {
    $this->expectExceptionObject(new MultipleItemsFoundException(2));

    $data = new $collection(['foo', 'bar', 'bar']);

    $data->sole(function ($value) {
        return $value === 'bar';
    });
})->with('collections');

test('hasSole', function (string $collection) {
    $collection = new $collection([
        ['age' => 2],
        ['age' => 3],
    ]);

    $this->assertFalse($collection->hasSole());
    $this->assertFalse($collection->where('age', 1)->hasSole());
    $this->assertTrue($collection->where('age', 2)->hasSole());

    $this->assertFalse($collection->hasSole(fn () => true));
    $this->assertFalse($collection->hasSole(fn () => false));
    $this->assertTrue($collection->hasSole(fn ($item) => $item['age'] === 2));

    $this->assertFalse($collection->hasSole('age', '>', 1));
    $this->assertFalse($collection->hasSole('age', '<', 1));
    $this->assertTrue($collection->hasSole('age', 2));

    $data = new $collection([
        (object) ['active' => true, 'verified' => true],
        (object) ['active' => false, 'verified' => true],
    ]);

    $this->assertFalse($data->hasSole->verified);
})->with('collections');

test('hasMany', function (string $collection) {
    $collection = new $collection([
        ['age' => 2],
        ['age' => 3],
    ]);

    $this->assertTrue($collection->hasMany());
    $this->assertFalse($collection->where('age', 1)->hasMany());
    $this->assertFalse($collection->where('age', 2)->hasMany());

    $this->assertTrue($collection->hasMany(fn () => true));
    $this->assertFalse($collection->hasMany(fn () => false));
    $this->assertFalse($collection->hasMany(fn ($item) => $item['age'] === 2));

    $this->assertTrue($collection->hasMany('age', '>', 1));
    $this->assertFalse($collection->hasMany('age', '<', 1));
    $this->assertFalse($collection->hasMany('age', 2));

    $data = new $collection([
        (object) ['active' => true, 'verified' => true],
        (object) ['active' => false, 'verified' => true],
    ]);

    $this->assertTrue($data->hasMany->verified);
})->with('collections');

test('firstOrFailReturnsFirstItemInCollection', function (string $collection) {
    $collection = new $collection([
        ['name' => 'foo'],
        ['name' => 'bar'],
    ]);

    $this->assertSame(['name' => 'foo'], $collection->where('name', 'foo')->firstOrFail());
    $this->assertSame(['name' => 'foo'], $collection->firstOrFail('name', '=', 'foo'));
    $this->assertSame(['name' => 'foo'], $collection->firstOrFail('name', 'foo'));
})->with('collections');

test('firstOrFailThrowsExceptionIfNoItemsExist', function (string $collection) {
    $this->expectException(ItemNotFoundException::class);

    $collection = new $collection([
        ['name' => 'foo'],
        ['name' => 'bar'],
    ]);

    $collection->where('name', 'INVALID')->firstOrFail();
})->with('collections');

test('firstOrFailDoesntThrowExceptionIfMoreThanOneItemExists', function (string $collection) {
    $collection = new $collection([
        ['name' => 'foo'],
        ['name' => 'foo'],
        ['name' => 'bar'],
    ]);

    $this->assertSame(['name' => 'foo'], $collection->where('name', 'foo')->firstOrFail());
})->with('collections');

test('firstOrFailReturnsFirstItemInCollectionIfOnlyOneExistsWithCallback', function (string $collection) {
    $data = new $collection(['foo', 'bar', 'baz']);
    $result = $data->firstOrFail(function ($value) {
        return $value === 'bar';
    });
    $this->assertSame('bar', $result);
})->with('collections');

test('firstOrFailThrowsExceptionIfNoItemsExistWithCallback', function (string $collection) {
    $this->expectException(ItemNotFoundException::class);

    $data = new $collection(['foo', 'bar', 'baz']);

    $data->firstOrFail(function ($value) {
        return $value === 'invalid';
    });
})->with('collections');

test('firstOrFailDoesntThrowExceptionIfMoreThanOneItemExistsWithCallback', function (string $collection) {
    $data = new $collection(['foo', 'bar', 'bar']);

    $this->assertSame(
        'bar',
        $data->firstOrFail(function ($value) {
            return $value === 'bar';
        })
    );
})->with('collections');

test('firstOrFailStopsIteratingAtFirstMatch', function (string $collection) {
    $data = new $collection([
        function () {
            return false;
        },
        function () {
            return true;
        },
        function () {
            throw new Exception();
        },
    ]);

    $this->assertNotNull($data->firstOrFail(function ($callback) {
        return $callback();
    }));
})->with('collections');

test('firstWhere', function (string $collection) {
    $data = new $collection([
        ['material' => 'paper', 'type' => 'book'],
        ['material' => 'rubber', 'type' => 'gasket'],
    ]);

    $this->assertSame('book', $data->firstWhere('material', 'paper')['type']);
    $this->assertSame('gasket', $data->firstWhere('material', 'rubber')['type']);
    $this->assertNull($data->firstWhere('material', 'nonexistent'));
    $this->assertNull($data->firstWhere('nonexistent', 'key'));

    $this->assertSame('book', $data->firstWhere(fn ($value) => $value['material'] === 'paper')['type']);
    $this->assertSame('gasket', $data->firstWhere(fn ($value) => $value['material'] === 'rubber')['type']);
    $this->assertNull($data->firstWhere(fn ($value) => $value['material'] === 'nonexistent'));
    $this->assertNull($data->firstWhere(fn ($value) => ($value['nonexistent'] ?? null) === 'key'));
})->with('collections');

test('firstWhereUsingEnum', function (string $collection) {
    $data = new $collection([
        ['id' => 1, 'name' => StaffEnum::Taylor],
        ['id' => 2, 'name' => StaffEnum::Joe],
        ['id' => 3, 'name' => StaffEnum::James],
    ]);

    $this->assertSame(1, $data->firstWhere('name', 'Taylor')['id']);
    $this->assertSame(2, $data->firstWhere('name', StaffEnum::Joe)['id']);
    $this->assertSame(3, $data->firstWhere('name', StaffEnum::James)['id']);
})->with('collections');

test('lastReturnsLastItemInCollection', function (string $collection) {
    $c = new $collection(['foo', 'bar']);
    $this->assertSame('bar', $c->last());

    $c = new $collection([]);
    $this->assertNull($c->last());
})->with('collections');

test('lastWithCallback', function (string $collection) {
    $data = new $collection([100, 200, 300]);
    $result = $data->last(function ($value) {
        return $value < 250;
    });
    $this->assertEquals(200, $result);

    $result = $data->last(function ($value, $key) {
        return $key < 2;
    });
    $this->assertEquals(200, $result);

    $result = $data->last(function ($value) {
        return $value > 300;
    });
    $this->assertNull($result);
})->with('collections');

test('lastWithCallbackAndDefault', function (string $collection) {
    $data = new $collection(['foo', 'bar']);
    $result = $data->last(function ($value) {
        return $value === 'baz';
    }, 'default');
    $this->assertSame('default', $result);

    $data = new $collection(['foo', 'bar', 'Bar']);
    $result = $data->last(function ($value) {
        return $value === 'bar';
    }, 'default');
    $this->assertSame('bar', $result);
})->with('collections');

test('lastWithDefaultAndWithoutCallback', function (string $collection) {
    $data = new $collection;
    $result = $data->last(null, 'default');
    $this->assertSame('default', $result);
})->with('collections');

test('popReturnsAndRemovesLastItemInCollection', function () {
    $c = new Collection(['foo', 'bar']);

    $this->assertSame('bar', $c->pop());
    $this->assertSame('foo', $c->first());
});

test('popReturnsAndRemovesLastXItemsInCollection', function () {
    $c = new Collection(['foo', 'bar', 'baz']);

    $this->assertEquals(new Collection(['baz', 'bar']), $c->pop(2));
    $this->assertSame('foo', $c->first());

    $this->assertEquals(new Collection(['baz', 'bar', 'foo']), (new Collection(['foo', 'bar', 'baz']))->pop(6));
});

test('shiftReturnsAndRemovesFirstItemInCollection', function () {
    $data = new Collection(['Taylor', 'Otwell']);

    $this->assertSame('Taylor', $data->shift());
    $this->assertSame('Otwell', $data->first());
    $this->assertSame('Otwell', $data->shift());
    $this->assertNull($data->first());
});

test('shiftReturnsAndRemovesFirstXItemsInCollection', function () {
    $data = new Collection(['foo', 'bar', 'baz']);

    $this->assertEquals(new Collection(['foo', 'bar']), $data->shift(2));
    $this->assertSame('baz', $data->first());

    $this->assertEquals(new Collection(['foo', 'bar', 'baz']), (new Collection(['foo', 'bar', 'baz']))->shift(6));

    $data = new Collection(['foo', 'bar', 'baz']);

    $this->assertEquals(new Collection([]), $data->shift(0));
    $this->assertEquals(collect(['foo', 'bar', 'baz']), $data);

    $this->expectException('InvalidArgumentException');
    (new Collection(['foo', 'bar', 'baz']))->shift(-1);

    $this->expectException('InvalidArgumentException');
    (new Collection(['foo', 'bar', 'baz']))->shift(-2);
});

test('shiftReturnsNullOnEmptyCollection', function () {
    $itemFoo = new stdClass();
    $itemFoo->text = 'f';
    $itemBar = new stdClass();
    $itemBar->text = 'x';

    $items = collect([$itemFoo, $itemBar]);

    $foo = $items->shift();
    $bar = $items->shift();

    $this->assertSame('f', $foo?->text);
    $this->assertSame('x', $bar?->text);
    $this->assertNull($items->shift());
});

test('sliding', function (string $collection) {
    // Default parameters: $size = 2, $step = 1
    $this->assertSame([], $collection::times(0)->sliding()->toArray());
    $this->assertSame([], $collection::times(1)->sliding()->toArray());
    $this->assertSame([[1, 2]], $collection::times(2)->sliding()->toArray());
    $this->assertSame(
        [[1, 2], [2, 3]],
        $collection::times(3)->sliding()->map->values()->toArray()
    );

    // Custom step: $size = 2, $step = 3
    $this->assertSame([], $collection::times(1)->sliding(2, 3)->toArray());
    $this->assertSame([[1, 2]], $collection::times(2)->sliding(2, 3)->toArray());
    $this->assertSame([[1, 2]], $collection::times(3)->sliding(2, 3)->toArray());
    $this->assertSame([[1, 2]], $collection::times(4)->sliding(2, 3)->toArray());
    $this->assertSame(
        [[1, 2], [4, 5]],
        $collection::times(5)->sliding(2, 3)->map->values()->toArray()
    );

    // Custom size: $size = 3, $step = 1
    $this->assertSame([], $collection::times(2)->sliding(3)->toArray());
    $this->assertSame([[1, 2, 3]], $collection::times(3)->sliding(3)->toArray());
    $this->assertSame(
        [[1, 2, 3], [2, 3, 4]],
        $collection::times(4)->sliding(3)->map->values()->toArray()
    );
    $this->assertSame(
        [[1, 2, 3], [2, 3, 4]],
        $collection::times(4)->sliding(3)->map->values()->toArray()
    );

    // Custom size and custom step: $size = 3, $step = 2
    $this->assertSame([], $collection::times(2)->sliding(3, 2)->toArray());
    $this->assertSame([[1, 2, 3]], $collection::times(3)->sliding(3, 2)->toArray());
    $this->assertSame([[1, 2, 3]], $collection::times(4)->sliding(3, 2)->toArray());
    $this->assertSame(
        [[1, 2, 3], [3, 4, 5]],
        $collection::times(5)->sliding(3, 2)->map->values()->toArray()
    );
    $this->assertSame(
        [[1, 2, 3], [3, 4, 5]],
        $collection::times(6)->sliding(3, 2)->map->values()->toArray()
    );

    // Ensure keys are preserved, and inner chunks are also collections
    $chunks = $collection::times(3)->sliding();

    $this->assertSame([[0 => 1, 1 => 2], [1 => 2, 2 => 3]], $chunks->toArray());

    $this->assertInstanceOf($collection, $chunks);
    $this->assertInstanceOf($collection, $chunks->first());
    $this->assertInstanceOf($collection, $chunks->skip(1)->first());

    // Test invalid size parameter (size must be at least 1)
    // instead of throwing an error. Now it throws InvalidArgumentException.
    try {
        $collection::times(5)->sliding(0, 1)->toArray();
        $this->fail('Expected InvalidArgumentException for size = 0');
    } catch (InvalidArgumentException $e) {
        $this->assertSame('Size value must be at least 1.', $e->getMessage());
    }

    try {
        $collection::times(5)->sliding(-1, 1)->toArray();
        $this->fail('Expected InvalidArgumentException for size = -1');
    } catch (InvalidArgumentException $e) {
        $this->assertSame('Size value must be at least 1.', $e->getMessage());
    }

    // Test invalid step parameter (step must be at least 1)
    // Now it throws InvalidArgumentException with an error message.
    try {
        $collection::times(5)->sliding(2, 0)->toArray();
        $this->fail('Expected InvalidArgumentException for step = 0');
    } catch (InvalidArgumentException $e) {
        $this->assertSame('Step value must be at least 1.', $e->getMessage());
    }

    try {
        $collection::times(5)->sliding(2, -1)->toArray();
        $this->fail('Expected InvalidArgumentException for step = -1');
    } catch (InvalidArgumentException $e) {
        $this->assertSame('Step value must be at least 1.', $e->getMessage());
    }
})->with('collections');
