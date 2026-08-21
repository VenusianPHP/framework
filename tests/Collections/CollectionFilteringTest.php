<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (higher-order
 * proxies over filter/unique/keyBy, where*, values, value, whereBetween,
 * flatten).
 */

use Tests\Collections\StaffEnum;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\HtmlString;

test('higherOrderKeyBy', function (string $collection) {
    $c = new $collection([
        ['id' => 'id1', 'name' => 'first'],
        ['id' => 'id2', 'name' => 'second'],
    ]);

    $this->assertEquals(['id1' => 'first', 'id2' => 'second'], $c->keyBy->id->map->name->all());
})->with('collections');

test('higherOrderUnique', function (string $collection) {
    $c = new $collection([
        ['id' => '1', 'name' => 'first'],
        ['id' => '1', 'name' => 'second'],
    ]);

    $this->assertCount(1, $c->unique->id);
})->with('collections');

test('higherOrderFilter', function (string $collection) {
    $c = new $collection([
        new class
        {
            public $name = 'Alex';

            public function active()
            {
                return true;
            }
        },
        new class
        {
            public $name = 'John';

            public function active()
            {
                return false;
            }
        },
    ]);

    $this->assertCount(1, $c->filter->active());
})->with('collections');

test('where', function (string $collection) {
    $c = new $collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);

    $this->assertEquals(
        [['v' => 3], ['v' => '3']],
        $c->where('v', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 3], ['v' => '3']],
        $c->where('v', '=', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 3], ['v' => '3']],
        $c->where('v', '==', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 3], ['v' => '3']],
        $c->where('v', 'garbage', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 3]],
        $c->where('v', '===', 3)->values()->all()
    );

    $this->assertEquals(
        [['v' => 1], ['v' => 2], ['v' => 4]],
        $c->where('v', '<>', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 1], ['v' => 2], ['v' => 4]],
        $c->where('v', '!=', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 1], ['v' => 2], ['v' => '3'], ['v' => 4]],
        $c->where('v', '!==', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3']],
        $c->where('v', '<=', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 3], ['v' => '3'], ['v' => 4]],
        $c->where('v', '>=', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 1], ['v' => 2]],
        $c->where('v', '<', 3)->values()->all()
    );
    $this->assertEquals(
        [['v' => 4]],
        $c->where('v', '>', 3)->values()->all()
    );

    $object = (object) ['foo' => 'bar'];

    $this->assertEquals(
        [],
        $c->where('v', $object)->values()->all()
    );

    $this->assertEquals(
        [['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]],
        $c->where('v', '<>', $object)->values()->all()
    );

    $this->assertEquals(
        [['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]],
        $c->where('v', '!=', $object)->values()->all()
    );

    $this->assertEquals(
        [['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]],
        $c->where('v', '!==', $object)->values()->all()
    );

    $this->assertEquals(
        [],
        $c->where('v', '>', $object)->values()->all()
    );

    $this->assertEquals(
        [['v' => 3], ['v' => '3']],
        $c->where(fn ($value) => $value['v'] == 3)->values()->all()
    );

    $this->assertEquals(
        [['v' => 3]],
        $c->where(fn ($value) => $value['v'] === 3)->values()->all()
    );

    $c = new $collection([['v' => 1], ['v' => $object]]);
    $this->assertEquals(
        [['v' => $object]],
        $c->where('v', $object)->values()->all()
    );

    $this->assertEquals(
        [['v' => 1], ['v' => $object]],
        $c->where('v', '<>', null)->values()->all()
    );

    $this->assertEquals(
        [],
        $c->where('v', '<', null)->values()->all()
    );

    $c = new $collection([['v' => 1], ['v' => new HtmlString('hello')]]);
    $this->assertEquals(
        [['v' => new HtmlString('hello')]],
        $c->where('v', 'hello')->values()->all()
    );

    $c = new $collection([['v' => 1], ['v' => 'hello']]);
    $this->assertEquals(
        [['v' => 'hello']],
        $c->where('v', new HtmlString('hello'))->values()->all()
    );

    $c = new $collection([['v' => 1], ['v' => 2], ['v' => null]]);
    $this->assertEquals(
        [['v' => 1], ['v' => 2]],
        $c->where('v')->values()->all()
    );

    $c = new $collection([
        ['v' => 1, 'g' => 3],
        ['v' => 2, 'g' => 2],
        ['v' => 2, 'g' => 3],
        ['v' => 2, 'g' => null],
    ]);
    $this->assertEquals([['v' => 2, 'g' => 3]], $c->where('v', 2)->where('g', 3)->values()->all());
    $this->assertEquals([['v' => 2, 'g' => 3]], $c->where('v', 2)->where('g', '>', 2)->values()->all());
    $this->assertEquals([], $c->where('v', 2)->where('g', 4)->values()->all());
    $this->assertEquals([['v' => 2, 'g' => null]], $c->where('v', 2)->whereNull('g')->values()->all());
})->with('collections');

test('whereStrict', function (string $collection) {
    $c = new $collection([['v' => 3], ['v' => '3']]);

    $this->assertEquals(
        [['v' => 3]],
        $c->whereStrict('v', 3)->values()->all()
    );
})->with('collections');

test('whereInstanceOf', function (string $collection) {
    $c = new $collection([new stdClass, new stdClass, new $collection, new stdClass, new Str]);
    $this->assertCount(3, $c->whereInstanceOf(stdClass::class));

    $this->assertCount(4, $c->whereInstanceOf([stdClass::class, Str::class]));
})->with('collections');

test('whereIn', function (string $collection) {
    $c = new $collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);
    $this->assertEquals([['v' => 1], ['v' => 3], ['v' => '3']], $c->whereIn('v', [1, 3])->values()->all());
    $this->assertEquals([], $c->whereIn('v', [2])->whereIn('v', [1, 3])->values()->all());
    $this->assertEquals([['v' => 1]], $c->whereIn('v', [1])->whereIn('v', [1, 3])->values()->all());
})->with('collections');

test('whereInStrict', function (string $collection) {
    $c = new $collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);
    $this->assertEquals([['v' => 1], ['v' => 3]], $c->whereInStrict('v', [1, 3])->values()->all());
})->with('collections');

test('whereNotIn', function (string $collection) {
    $c = new $collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);
    $this->assertEquals([['v' => 2], ['v' => 4]], $c->whereNotIn('v', [1, 3])->values()->all());
    $this->assertEquals([['v' => 4]], $c->whereNotIn('v', [2])->whereNotIn('v', [1, 3])->values()->all());
})->with('collections');

test('whereNotInStrict', function (string $collection) {
    $c = new $collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);
    $this->assertEquals([['v' => 2], ['v' => '3'], ['v' => 4]], $c->whereNotInStrict('v', [1, 3])->values()->all());
})->with('collections');

test('values', function (string $collection) {
    $c = new $collection([['id' => 1, 'name' => 'Hello'], ['id' => 2, 'name' => 'World']]);
    $this->assertEquals([['id' => 2, 'name' => 'World']], $c->filter(function ($item) {
        return $item['id'] == 2;
    })->values()->all());
})->with('collections');

test('valuesResetKey', function (string $collection) {
    $data = new $collection([1 => 'a', 2 => 'b', 3 => 'c']);
    $this->assertEquals([0 => 'a', 1 => 'b', 2 => 'c'], $data->values()->all());
})->with('collections');

test('value', function (string $collection) {
    $c = new $collection([['id' => 1, 'name' => 'Hello'], ['id' => 2, 'name' => 'World']]);

    $this->assertEquals('Hello', $c->value('name'));
    $this->assertEquals('World', $c->where('id', 2)->value('name'));

    $c = new $collection([
        ['id' => 1, 'pivot' => ['value' => 'foo']],
        ['id' => 2, 'pivot' => ['value' => 'bar']],
    ]);

    $this->assertEquals(['value' => 'foo'], $c->value('pivot'));
    $this->assertEquals('foo', $c->value('pivot.value'));
    $this->assertEquals('bar', $c->where('id', 2)->value('pivot.value'));
})->with('collections');

test('valueUsingEnum', function (string $collection) {
    $c = new $collection([['id' => 1, 'name' => StaffEnum::Taylor], ['id' => 2, 'name' => StaffEnum::Joe]]);

    $this->assertSame(StaffEnum::Taylor, $c->value('name'));
    $this->assertEquals(StaffEnum::Joe, $c->where('id', 2)->value('name'));
})->with('collections');

test('valueWithNegativeValue', function (string $collection) {
    $c = new $collection([['id' => 1, 'balance' => 0], ['id' => 2, 'balance' => 200]]);

    $this->assertEquals(0, $c->value('balance'));

    $c = new $collection([['id' => 1, 'balance' => ''], ['id' => 2, 'balance' => 200]]);

    $this->assertEquals('', $c->value('balance'));

    $c = new $collection([['id' => 1, 'balance' => null], ['id' => 2, 'balance' => 200]]);

    $this->assertEquals(null, $c->value('balance'));

    $c = new $collection([['id' => 1], ['id' => 2, 'balance' => 200]]);

    $this->assertEquals(200, $c->value('balance'));

    $c = new $collection([['id' => 1], ['id' => 2, 'balance' => 0], ['id' => 3, 'balance' => 200]]);

    $this->assertEquals(0, $c->value('balance'));
})->with('collections');

test('valueWithObjects', function (string $collection) {
    $c = new $collection([
        literal(id: 1),
        literal(id: 2, balance: ''),
        literal(id: 3, balance: 200),
    ]);

    $this->assertEquals('', $c->value('balance'));

    $c = new $collection([
        literal(id: 1),
        literal(id: 2, balance: literal(currency: 'USD', value: 0)),
        literal(id: 3, balance: literal(currency: 'USD', value: 200)),
    ]);

    $this->assertEquals(0, $c->value('balance.value'));
})->with('collections');

test('between', function (string $collection) {
    $c = new $collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);

    $this->assertEquals([['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]],
        $c->whereBetween('v', [2, 4])->values()->all());
    $this->assertEquals([['v' => 1]], $c->whereBetween('v', [-1, 1])->all());
    $this->assertEquals([['v' => 3], ['v' => '3']], $c->whereBetween('v', [3, 3])->values()->all());
})->with('collections');

test('whereNotBetween', function (string $collection) {
    $c = new $collection([['v' => 1], ['v' => 2], ['v' => 3], ['v' => '3'], ['v' => 4]]);

    $this->assertEquals([['v' => 1]], $c->whereNotBetween('v', [2, 4])->values()->all());
    $this->assertEquals([['v' => 2], ['v' => 3], ['v' => 3], ['v' => 4]], $c->whereNotBetween('v', [-1, 1])->values()->all());
    $this->assertEquals([['v' => 1], ['v' => '2'], ['v' => '4']], $c->whereNotBetween('v', [3, 3])->values()->all());
})->with('collections');

test('flatten', function (string $collection) {
    // Flat arrays are unaffected
    $c = new $collection(['#foo', '#bar', '#baz']);
    $this->assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

    // Nested arrays are flattened with existing flat items
    $c = new $collection([['#foo', '#bar'], '#baz']);
    $this->assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

    // Sets of nested arrays are flattened
    $c = new $collection([['#foo', '#bar'], ['#baz']]);
    $this->assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

    // Deeply nested arrays are flattened
    $c = new $collection([['#foo', ['#bar']], ['#baz']]);
    $this->assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

    // Nested collections are flattened alongside arrays
    $c = new $collection([new $collection(['#foo', '#bar']), ['#baz']]);
    $this->assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

    // Nested collections containing plain arrays are flattened
    $c = new $collection([new $collection(['#foo', ['#bar']]), ['#baz']]);
    $this->assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

    // Nested arrays containing collections are flattened
    $c = new $collection([['#foo', new $collection(['#bar'])], ['#baz']]);
    $this->assertEquals(['#foo', '#bar', '#baz'], $c->flatten()->all());

    // Nested arrays containing collections containing arrays are flattened
    $c = new $collection([['#foo', new $collection(['#bar', ['#zap']])], ['#baz']]);
    $this->assertEquals(['#foo', '#bar', '#zap', '#baz'], $c->flatten()->all());
})->with('collections');

test('flattenWithDepth', function (string $collection) {
    // No depth flattens recursively
    $c = new $collection([['#foo', ['#bar', ['#baz']]], '#zap']);
    $this->assertEquals(['#foo', '#bar', '#baz', '#zap'], $c->flatten()->all());

    // Specifying a depth only flattens to that depth
    $c = new $collection([['#foo', ['#bar', ['#baz']]], '#zap']);
    $this->assertEquals(['#foo', ['#bar', ['#baz']], '#zap'], $c->flatten(1)->all());

    $c = new $collection([['#foo', ['#bar', ['#baz']]], '#zap']);
    $this->assertEquals(['#foo', '#bar', ['#baz'], '#zap'], $c->flatten(2)->all());
})->with('collections');

test('flattenIgnoresKeys', function (string $collection) {
    // No depth ignores keys
    $c = new $collection(['#foo', ['key' => '#bar'], ['key' => '#baz'], 'key' => '#zap']);
    $this->assertEquals(['#foo', '#bar', '#baz', '#zap'], $c->flatten()->all());

    // Depth of 1 ignores keys
    $c = new $collection(['#foo', ['key' => '#bar'], ['key' => '#baz'], 'key' => '#zap']);
    $this->assertEquals(['#foo', '#bar', '#baz', '#zap'], $c->flatten(1)->all());
})->with('collections');
