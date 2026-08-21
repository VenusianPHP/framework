<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (every, except,
 * pluck family, has, hasAny, implode, take, getOrPut, put, random, takeLast,
 * takeUntil family, takeWhile family).
 */

use Tests\Collections\Fixtures\TestArrayAccessImplementation;
use Tests\Collections\Fixtures\TestSupportCollectionHigherOrderItem;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Stringable;

test('every', function (string $collection) {
    $c = new $collection([]);
    $this->assertTrue($c->every('key', 'value'));
    $this->assertTrue($c->every(function () {
        return false;
    }));

    $c = new $collection([['age' => 18], ['age' => 20], ['age' => 20]]);
    $this->assertFalse($c->every('age', 18));
    $this->assertTrue($c->every('age', '>=', 18));
    $this->assertTrue($c->every(function ($item) {
        return $item['age'] >= 18;
    }));
    $this->assertFalse($c->every(function ($item) {
        return $item['age'] >= 20;
    }));

    $c = new $collection([null, null]);
    $this->assertTrue($c->every(function ($item) {
        return $item === null;
    }));

    $c = new $collection([['active' => true], ['active' => true]]);
    $this->assertTrue($c->every('active'));
    $this->assertTrue($c->every->active);
    $this->assertFalse($c->concat([['active' => false]])->every->active);
})->with('collections');

test('except', function (string $collection) {
    $data = new $collection(['first' => 'Taylor', 'last' => 'Otwell', 'email' => 'taylorotwell@gmail.com']);

    $this->assertEquals($data->all(), $data->except(null)->all());
    $this->assertEquals(['first' => 'Taylor'], $data->except(['last', 'email', 'missing'])->all());
    $this->assertEquals(['first' => 'Taylor'], $data->except('last', 'email', 'missing')->all());
    $this->assertEquals(['first' => 'Taylor'], $data->except(collect(['last', 'email', 'missing']))->all());

    $this->assertEquals(['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'], $data->except(['last'])->all());
    $this->assertEquals(['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'], $data->except('last')->all());
    $this->assertEquals(['first' => 'Taylor', 'email' => 'taylorotwell@gmail.com'], $data->except(collect(['last']))->all());
})->with('collections');

test('exceptSelf', function (string $collection) {
    $data = new $collection(['first' => 'Taylor', 'last' => 'Otwell']);
    $this->assertEquals(['first' => 'Taylor', 'last' => 'Otwell'], $data->except($data)->all());
})->with('collections');

test('pluckWithArrayAndObjectValues', function (string $collection) {
    $data = new $collection([(object) ['name' => 'taylor', 'email' => 'foo'], ['name' => 'dayle', 'email' => 'bar']]);
    $this->assertEquals(['taylor' => 'foo', 'dayle' => 'bar'], $data->pluck('email', 'name')->all());
    $this->assertEquals(['foo', 'bar'], $data->pluck('email')->all());
})->with('collections');

test('pluckWithArrayAccessValues', function (string $collection) {
    $data = new $collection([
        new TestArrayAccessImplementation(['name' => 'taylor', 'email' => 'foo']),
        new TestArrayAccessImplementation(['name' => 'dayle', 'email' => 'bar']),
    ]);

    $this->assertEquals(['taylor' => 'foo', 'dayle' => 'bar'], $data->pluck('email', 'name')->all());
    $this->assertEquals(['foo', 'bar'], $data->pluck('email')->all());
})->with('collections');

test('pluckWithDotNotation', function (string $collection) {
    $data = new $collection([
        [
            'name' => 'amir',
            'skill' => [
                'backend' => ['php', 'python'],
            ],
        ],
        [
            'name' => 'taylor',
            'skill' => [
                'backend' => ['php', 'asp', 'java'],
            ],
        ],
    ]);

    $this->assertEquals([['php', 'python'], ['php', 'asp', 'java']], $data->pluck('skill.backend')->all());
})->with('collections');

test('pluckWithClosure', function (string $collection) {
    $data = new $collection([
        [
            'name' => 'amir',
            'skill' => [
                'backend' => ['php', 'python'],
            ],
        ],
        [
            'name' => 'taylor',
            'skill' => [
                'backend' => ['php', 'asp', 'java'],
            ],
        ],
    ]);

    $this->assertEquals(['amir (verified)', 'taylor (verified)'], $data->pluck(fn (array $row) => "{$row['name']} (verified)")->all());
    $this->assertEquals(['php/python' => 'amir', 'php/asp/java' => 'taylor'], $data->pluck('name', fn (array $row) => implode('/', $row['skill']['backend']))->all());
})->with('collections');

test('pluckDuplicateKeysExist', function (string $collection) {
    $data = new $collection([
        ['brand' => 'Tesla', 'color' => 'red'],
        ['brand' => 'Pagani', 'color' => 'white'],
        ['brand' => 'Tesla', 'color' => 'black'],
        ['brand' => 'Pagani', 'color' => 'orange'],
    ]);

    $this->assertEquals(['Tesla' => 'black', 'Pagani' => 'orange'], $data->pluck('color', 'brand')->all());
})->with('collections');

test('has', function (string $collection) {
    $data = new $collection(['id' => 1, 'first' => 'Hello', 'second' => 'World']);
    $this->assertTrue($data->has('first'));
    $this->assertFalse($data->has('third'));
    $this->assertTrue($data->has(['first', 'second']));
    $this->assertFalse($data->has(['third', 'first']));
    $this->assertTrue($data->has('first', 'second'));
})->with('collections');

test('hasAny', function (string $collection) {
    $data = new $collection(['id' => 1, 'first' => 'Hello', 'second' => 'World']);

    $this->assertTrue($data->hasAny('first'));
    $this->assertFalse($data->hasAny('third'));
    $this->assertTrue($data->hasAny(['first', 'second']));
    $this->assertTrue($data->hasAny(['first', 'fourth']));
    $this->assertFalse($data->hasAny(['third', 'fourth']));
    $this->assertFalse($data->hasAny('third', 'fourth'));
    $this->assertFalse($data->hasAny([]));
})->with('collections');

test('implode', function (string $collection) {
    $data = new $collection([['name' => 'taylor', 'email' => 'foo'], ['name' => 'dayle', 'email' => 'bar']]);
    $this->assertSame('foobar', $data->implode('email'));
    $this->assertSame('foo,bar', $data->implode('email', ','));

    $data = new $collection(['taylor', 'dayle']);
    $this->assertSame('taylordayle', $data->implode(''));
    $this->assertSame('taylor,dayle', $data->implode(','));

    $data = new $collection([
        ['name' => new Stringable('taylor'), 'email' => new Stringable('foo')],
        ['name' => new Stringable('dayle'), 'email' => new Stringable('bar')],
    ]);
    $this->assertSame('foobar', $data->implode('email'));
    $this->assertSame('foo,bar', $data->implode('email', ','));

    $data = new $collection([new Stringable('taylor'), new Stringable('dayle')]);
    $this->assertSame('taylordayle', $data->implode(''));
    $this->assertSame('taylor,dayle', $data->implode(','));
    $this->assertSame('taylor_dayle', $data->implode('_'));

    $data = new $collection([['name' => 'taylor', 'email' => 'foo'], ['name' => 'dayle', 'email' => 'bar']]);
    $this->assertSame('taylor-foodayle-bar', $data->implode(fn ($user) => $user['name'].'-'.$user['email']));
    $this->assertSame('taylor-foo,dayle-bar', $data->implode(fn ($user) => $user['name'].'-'.$user['email'], ','));
})->with('collections');

// testImplodeModels is cut: it builds anonymous classes extending Eloquent's
// Model to exercise implode() over accessor-backed attributes. Collections
// must never depend on the Database component (see AGENTS.md dependency
// direction), and implode()'s pluck-when-not-Stringable branch is already
// covered above via TestArrayAccessImplementation-style array/object values.

test('take', function (string $collection) {
    $data = new $collection(['taylor', 'dayle', 'shawn']);
    $data = $data->take(2);
    $this->assertEquals(['taylor', 'dayle'], $data->all());
})->with('collections');

test('getOrPut', function () {
    $data = new Collection(['name' => 'taylor', 'email' => 'foo']);

    $this->assertSame('taylor', $data->getOrPut('name', null));
    $this->assertSame('foo', $data->getOrPut('email', null));
    $this->assertSame('male', $data->getOrPut('gender', 'male'));

    $this->assertSame('taylor', $data->get('name'));
    $this->assertSame('foo', $data->get('email'));
    $this->assertSame('male', $data->get('gender'));

    $data = new Collection(['name' => 'taylor', 'email' => 'foo']);

    $this->assertSame('taylor', $data->getOrPut('name', function () {
        return null;
    }));

    $this->assertSame('foo', $data->getOrPut('email', function () {
        return null;
    }));

    $this->assertSame('male', $data->getOrPut('gender', function () {
        return 'male';
    }));

    $this->assertSame('taylor', $data->get('name'));
    $this->assertSame('foo', $data->get('email'));
    $this->assertSame('male', $data->get('gender'));
});

test('getOrPutWithNoKey', function () {
    $data = new Collection(['taylor', 'shawn']);
    $this->assertSame('dayle', $data->getOrPut(null, 'dayle'));
    $this->assertSame('john', $data->getOrPut(null, 'john'));
    $this->assertSame(['taylor', 'shawn', 'dayle', 'john'], $data->all());

    $data = new Collection(['taylor', '' => 'shawn']);
    $this->assertSame('shawn', $data->getOrPut(null, 'dayle'));
    $this->assertSame(['taylor', '' => 'shawn'], $data->all());
});

test('put', function () {
    $data = new Collection(['name' => 'taylor', 'email' => 'foo']);
    $data = $data->put('name', 'dayle');
    $this->assertEquals(['name' => 'dayle', 'email' => 'foo'], $data->all());
});

test('putWithNoKey', function () {
    $data = new Collection(['taylor', 'shawn']);
    $data = $data->put(null, 'dayle');
    $this->assertEquals(['taylor', 'shawn', 'dayle'], $data->all());
});

test('random', function (string $collection) {
    $data = new $collection([1, 2, 3, 4, 5, 6]);

    $random = $data->random();
    $this->assertIsInt($random);
    $this->assertContains($random, $data->all());

    $random = $data->random(0);
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(0, $random);

    $random = $data->random(1);
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(1, $random);

    $random = $data->random(2);
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(2, $random);

    $random = $data->random('0');
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(0, $random);

    $random = $data->random('1');
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(1, $random);

    $random = $data->random('2');
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(2, $random);

    $random = $data->random(2, true);
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(2, $random);
    $this->assertCount(2, array_intersect_assoc($random->all(), $data->all()));

    $random = $data->random(fn ($items) => min(10, count($items)));
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(6, $random);

    $random = $data->random(fn ($items) => min(10, count($items) - 1), true);
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(5, $random);
    $this->assertCount(5, array_intersect_assoc($random->all(), $data->all()));
})->with('collections');

test('randomOnEmptyCollection', function (string $collection) {
    $data = new $collection;

    $random = $data->random(0);
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(0, $random);

    $random = $data->random('0');
    $this->assertInstanceOf($collection, $random);
    $this->assertCount(0, $random);
})->with('collections');

test('takeLast', function (string $collection) {
    $data = new $collection(['taylor', 'dayle', 'shawn']);
    $data = $data->take(-2);
    $this->assertEquals([1 => 'dayle', 2 => 'shawn'], $data->all());
})->with('collections');

test('takeUntilUsingValue', function (string $collection) {
    $data = new $collection([1, 2, 3, 4]);

    $data = $data->takeUntil(3);

    $this->assertSame([1, 2], $data->toArray());
})->with('collections');

test('takeUntilUsingCallback', function (string $collection) {
    $data = new $collection([1, 2, 3, 4]);

    $data = $data->takeUntil(function ($item) {
        return $item >= 3;
    });

    $this->assertSame([1, 2], $data->toArray());
})->with('collections');

test('takeUntilReturnsAllItemsForUnmetValue', function (string $collection) {
    $data = new $collection([1, 2, 3, 4]);

    $actual = $data->takeUntil(99);

    $this->assertSame($data->toArray(), $actual->toArray());

    $actual = $data->takeUntil(function ($item) {
        return $item >= 99;
    });

    $this->assertSame($data->toArray(), $actual->toArray());
})->with('collections');

test('takeUntilCanBeProxied', function (string $collection) {
    $data = new $collection([
        new TestSupportCollectionHigherOrderItem('Adam'),
        new TestSupportCollectionHigherOrderItem('Taylor'),
        new TestSupportCollectionHigherOrderItem('Jason'),
    ]);

    $actual = $data->takeUntil->is('Jason');

    $this->assertCount(2, $actual);
    $this->assertSame('Adam', $actual->get(0)->name);
    $this->assertSame('Taylor', $actual->get(1)->name);
})->with('collections');

test('takeWhileUsingValue', function (string $collection) {
    $data = new $collection([1, 1, 2, 2, 3, 3]);

    $data = $data->takeWhile(1);

    $this->assertSame([1, 1], $data->toArray());
})->with('collections');

test('takeWhileUsingCallback', function (string $collection) {
    $data = new $collection([1, 2, 3, 4]);

    $data = $data->takeWhile(function ($item) {
        return $item < 3;
    });

    $this->assertSame([1, 2], $data->toArray());
})->with('collections');

test('takeWhileReturnsNoItemsForUnmetValue', function (string $collection) {
    $data = new $collection([1, 2, 3, 4]);

    $actual = $data->takeWhile(2);

    $this->assertSame([], $actual->toArray());

    $actual = $data->takeWhile(function ($item) {
        return $item == 99;
    });

    $this->assertSame([], $actual->toArray());
})->with('collections');

test('takeWhileCanBeProxied', function (string $collection) {
    $data = new $collection([
        new TestSupportCollectionHigherOrderItem('Adam'),
        new TestSupportCollectionHigherOrderItem('Adam'),
        new TestSupportCollectionHigherOrderItem('Taylor'),
        new TestSupportCollectionHigherOrderItem('Taylor'),
    ]);

    $actual = $data->takeWhile->is('Adam');

    $this->assertCount(2, $actual);
    $this->assertSame('Adam', $actual->get(0)->name);
    $this->assertSame('Adam', $actual->get(1)->name);
})->with('collections');
