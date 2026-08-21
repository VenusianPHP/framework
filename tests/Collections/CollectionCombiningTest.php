<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (merge,
 * mergeRecursive, multiply, replace, replaceRecursive, union, diff family,
 * duplicates, each, eachSpread, intersect family, unique family).
 */

use Voyager\NutsAndBolts\Collection;

test('mergeNull', function (string $collection) {
    $c = new $collection(['name' => 'Hello']);
    $this->assertEquals(['name' => 'Hello'], $c->merge(null)->all());
})->with('collections');

test('mergeArray', function (string $collection) {
    $c = new $collection(['name' => 'Hello']);
    $this->assertEquals(['name' => 'Hello', 'id' => 1], $c->merge(['id' => 1])->all());
})->with('collections');

test('mergeCollection', function (string $collection) {
    $c = new $collection(['name' => 'Hello']);
    $this->assertEquals(['name' => 'World', 'id' => 1], $c->merge(new $collection(['name' => 'World', 'id' => 1]))->all());
})->with('collections');

test('mergeRecursiveNull', function (string $collection) {
    $c = new $collection(['name' => 'Hello']);
    $this->assertEquals(['name' => 'Hello'], $c->mergeRecursive(null)->all());
})->with('collections');

test('mergeRecursiveArray', function (string $collection) {
    $c = new $collection(['name' => 'Hello', 'id' => 1]);
    $this->assertEquals(['name' => 'Hello', 'id' => [1, 2]], $c->mergeRecursive(['id' => 2])->all());
})->with('collections');

test('mergeRecursiveCollection', function (string $collection) {
    $c = new $collection(['name' => 'Hello', 'id' => 1, 'meta' => ['tags' => ['a', 'b'], 'roles' => 'admin']]);
    $this->assertEquals(
        ['name' => 'Hello', 'id' => 1, 'meta' => ['tags' => ['a', 'b', 'c'], 'roles' => ['admin', 'editor']]],
        $c->mergeRecursive(new $collection(['meta' => ['tags' => ['c'], 'roles' => 'editor']]))->all()
    );
})->with('collections');

test('multiplyCollection', function (string $collection) {
    $c = new $collection(['Hello', 1, ['tags' => ['a', 'b'], 'admin']]);

    $this->assertEquals([], $c->multiply(-1)->all());
    $this->assertEquals([], $c->multiply(0)->all());

    $this->assertEquals(
        ['Hello', 1, ['tags' => ['a', 'b'], 'admin']],
        $c->multiply(1)->all()
    );

    $this->assertEquals(
        ['Hello', 1, ['tags' => ['a', 'b'], 'admin'], 'Hello', 1, ['tags' => ['a', 'b'], 'admin'], 'Hello', 1, ['tags' => ['a', 'b'], 'admin']],
        $c->multiply(3)->all()
    );
})->with('collections');

test('replaceNull', function (string $collection) {
    $c = new $collection(['a', 'b', 'c']);
    $this->assertEquals(['a', 'b', 'c'], $c->replace(null)->all());
})->with('collections');

test('replaceArray', function (string $collection) {
    $c = new $collection(['a', 'b', 'c']);
    $this->assertEquals(['a', 'd', 'e'], $c->replace([1 => 'd', 2 => 'e'])->all());

    $c = new $collection(['a', 'b', 'c']);
    $this->assertEquals(['a', 'd', 'e', 'f', 'g'], $c->replace([1 => 'd', 2 => 'e', 3 => 'f', 4 => 'g'])->all());

    $c = new $collection(['name' => 'amir', 'family' => 'otwell']);
    $this->assertEquals(['name' => 'taylor', 'family' => 'otwell', 'age' => 26], $c->replace(['name' => 'taylor', 'age' => 26])->all());
})->with('collections');

test('replaceCollection', function (string $collection) {
    $c = new $collection(['a', 'b', 'c']);
    $this->assertEquals(
        ['a', 'd', 'e'],
        $c->replace(new $collection([1 => 'd', 2 => 'e']))->all()
    );

    $c = new $collection(['a', 'b', 'c']);
    $this->assertEquals(
        ['a', 'd', 'e', 'f', 'g'],
        $c->replace(new $collection([1 => 'd', 2 => 'e', 3 => 'f', 4 => 'g']))->all()
    );

    $c = new $collection(['name' => 'amir', 'family' => 'otwell']);
    $this->assertEquals(
        ['name' => 'taylor', 'family' => 'otwell', 'age' => 26],
        $c->replace(new $collection(['name' => 'taylor', 'age' => 26]))->all()
    );
})->with('collections');

test('replaceRecursiveNull', function (string $collection) {
    $c = new $collection(['a', 'b', ['c', 'd']]);
    $this->assertEquals(['a', 'b', ['c', 'd']], $c->replaceRecursive(null)->all());
})->with('collections');

test('replaceRecursiveArray', function (string $collection) {
    $c = new $collection(['a', 'b', ['c', 'd']]);
    $this->assertEquals(['z', 'b', ['c', 'e']], $c->replaceRecursive(['z', 2 => [1 => 'e']])->all());

    $c = new $collection(['a', 'b', ['c', 'd']]);
    $this->assertEquals(['z', 'b', ['c', 'e'], 'f'], $c->replaceRecursive(['z', 2 => [1 => 'e'], 'f'])->all());
})->with('collections');

test('replaceRecursiveCollection', function (string $collection) {
    $c = new $collection(['a', 'b', ['c', 'd']]);
    $this->assertEquals(
        ['z', 'b', ['c', 'e']],
        $c->replaceRecursive(new $collection(['z', 2 => [1 => 'e']]))->all()
    );
})->with('collections');

test('unionNull', function (string $collection) {
    $c = new $collection(['name' => 'Hello']);
    $this->assertEquals(['name' => 'Hello'], $c->union(null)->all());
})->with('collections');

test('unionArray', function (string $collection) {
    $c = new $collection(['name' => 'Hello']);
    $this->assertEquals(['name' => 'Hello', 'id' => 1], $c->union(['id' => 1])->all());
})->with('collections');

test('unionCollection', function (string $collection) {
    $c = new $collection(['name' => 'Hello']);
    $this->assertEquals(['name' => 'Hello', 'id' => 1], $c->union(new $collection(['name' => 'World', 'id' => 1]))->all());
})->with('collections');

test('diffCollection', function (string $collection) {
    $c = new $collection(['id' => 1, 'first_word' => 'Hello']);
    $this->assertEquals(['id' => 1], $c->diff(new $collection(['first_word' => 'Hello', 'last_word' => 'World']))->all());
})->with('collections');

test('diffUsingWithCollection', function (string $collection) {
    $c = new $collection(['en_GB', 'fr', 'HR']);
    // demonstrate that diff won't support case insensitivity
    $this->assertEquals(['en_GB', 'fr', 'HR'], $c->diff(new $collection(['en_gb', 'hr']))->values()->toArray());
    // allow for case insensitive difference
    $this->assertEquals(['fr'], $c->diffUsing(new $collection(['en_gb', 'hr']), 'strcasecmp')->values()->toArray());
})->with('collections');

test('diffUsingWithNull', function (string $collection) {
    $c = new $collection(['en_GB', 'fr', 'HR']);
    $this->assertEquals(['en_GB', 'fr', 'HR'], $c->diffUsing(null, 'strcasecmp')->values()->toArray());
})->with('collections');

test('diffNull', function (string $collection) {
    $c = new $collection(['id' => 1, 'first_word' => 'Hello']);
    $this->assertEquals(['id' => 1, 'first_word' => 'Hello'], $c->diff(null)->all());
})->with('collections');

test('diffKeys', function (string $collection) {
    $c1 = new $collection(['id' => 1, 'first_word' => 'Hello']);
    $c2 = new $collection(['id' => 123, 'foo_bar' => 'Hello']);
    $this->assertEquals(['first_word' => 'Hello'], $c1->diffKeys($c2)->all());
})->with('collections');

test('diffKeysUsing', function (string $collection) {
    $c1 = new $collection(['id' => 1, 'first_word' => 'Hello']);
    $c2 = new $collection(['ID' => 123, 'foo_bar' => 'Hello']);
    // demonstrate that diffKeys won't support case insensitivity
    $this->assertEquals(['id' => 1, 'first_word' => 'Hello'], $c1->diffKeys($c2)->all());
    // allow for case insensitive difference
    $this->assertEquals(['first_word' => 'Hello'], $c1->diffKeysUsing($c2, 'strcasecmp')->all());
})->with('collections');

test('diffAssoc', function (string $collection) {
    $c1 = new $collection(['id' => 1, 'first_word' => 'Hello', 'not_affected' => 'value']);
    $c2 = new $collection(['id' => 123, 'foo_bar' => 'Hello', 'not_affected' => 'value']);
    $this->assertEquals(['id' => 1, 'first_word' => 'Hello'], $c1->diffAssoc($c2)->all());
})->with('collections');

test('diffAssocUsing', function (string $collection) {
    $c1 = new $collection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);
    $c2 = new $collection(['A' => 'green', 'yellow', 'red']);
    // demonstrate that the case of the keys will affect the output when diffAssoc is used
    $this->assertEquals(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red'], $c1->diffAssoc($c2)->all());
    // allow for case insensitive difference
    $this->assertEquals(['b' => 'brown', 'c' => 'blue', 'red'], $c1->diffAssocUsing($c2, 'strcasecmp')->all());
})->with('collections');

test('duplicates', function (string $collection) {
    $duplicates = $collection::make([1, 2, 1, 'laravel', null, 'laravel', 'php', null])->duplicates()->all();
    $this->assertSame([2 => 1, 5 => 'laravel', 7 => null], $duplicates);

    // does loose comparison
    $duplicates = $collection::make([2, '2', [], null])->duplicates()->all();
    $this->assertSame([1 => '2', 3 => null], $duplicates);

    // works with mix of primitives
    $duplicates = $collection::make([1, '2', ['laravel'], ['laravel'], null, '2'])->duplicates()->all();
    $this->assertSame([3 => ['laravel'], 5 => '2'], $duplicates);

    // works with mix of objects and primitives **excepts numbers**.
    $expected = new Collection(['laravel']);
    $duplicates = $collection::make([new Collection(['laravel']), $expected, $expected, [], '2', '2'])->duplicates()->all();
    $this->assertSame([1 => $expected, 2 => $expected, 5 => '2'], $duplicates);
})->with('collections');

test('duplicatesWithKey', function (string $collection) {
    $items = [['framework' => 'vue'], ['framework' => 'laravel'], ['framework' => 'laravel']];
    $duplicates = $collection::make($items)->duplicates('framework')->all();
    $this->assertSame([2 => 'laravel'], $duplicates);

    // works with key and strict
    $items = [['Framework' => 'vue'], ['framework' => 'vue'], ['Framework' => 'vue']];
    $duplicates = $collection::make($items)->duplicates('Framework', true)->all();
    $this->assertSame([2 => 'vue'], $duplicates);
})->with('collections');

test('duplicatesWithCallback', function (string $collection) {
    $items = [['framework' => 'vue'], ['framework' => 'laravel'], ['framework' => 'laravel']];
    $duplicates = $collection::make($items)->duplicates(function ($item) {
        return $item['framework'];
    })->all();
    $this->assertSame([2 => 'laravel'], $duplicates);
})->with('collections');

test('duplicatesWithStrict', function (string $collection) {
    $duplicates = $collection::make([1, 2, 1, 'laravel', null, 'laravel', 'php', null])->duplicatesStrict()->all();
    $this->assertSame([2 => 1, 5 => 'laravel', 7 => null], $duplicates);

    // does strict comparison
    $duplicates = $collection::make([2, '2', [], null])->duplicatesStrict()->all();
    $this->assertSame([], $duplicates);

    // works with mix of primitives
    $duplicates = $collection::make([1, '2', ['laravel'], ['laravel'], null, '2'])->duplicatesStrict()->all();
    $this->assertSame([3 => ['laravel'], 5 => '2'], $duplicates);

    // works with mix of primitives, objects, and numbers
    $expected = new $collection(['laravel']);
    $duplicates = $collection::make([new $collection(['laravel']), $expected, $expected, [], '2', '2'])->duplicatesStrict()->all();
    $this->assertSame([2 => $expected, 5 => '2'], $duplicates);
})->with('collections');

test('each', function (string $collection) {
    $c = new $collection($original = [1, 2, 'foo' => 'bar', 'bam' => 'baz']);

    $result = [];
    $c->each(function ($item, $key) use (&$result) {
        $result[$key] = $item;
    });
    $this->assertEquals($original, $result);

    $result = [];
    $c->each(function ($item, $key) use (&$result) {
        $result[$key] = $item;
        if (is_string($key)) {
            return false;
        }
    });
    $this->assertEquals([1, 2, 'foo' => 'bar'], $result);
})->with('collections');

test('eachSpread', function (string $collection) {
    $c = new $collection([[1, 'a'], [2, 'b']]);

    $result = [];
    $c->eachSpread(function ($number, $character) use (&$result) {
        $result[] = [$number, $character];
    });
    $this->assertEquals($c->all(), $result);

    $result = [];
    $c->eachSpread(function ($number, $character) use (&$result) {
        $result[] = [$number, $character];

        return false;
    });
    $this->assertEquals([[1, 'a']], $result);

    $result = [];
    $c->eachSpread(function ($number, $character, $key) use (&$result) {
        $result[] = [$number, $character, $key];
    });
    $this->assertEquals([[1, 'a', 0], [2, 'b', 1]], $result);

    $c = new $collection([new Collection([1, 'a']), new Collection([2, 'b'])]);
    $result = [];
    $c->eachSpread(function ($number, $character, $key) use (&$result) {
        $result[] = [$number, $character, $key];
    });
    $this->assertEquals([[1, 'a', 0], [2, 'b', 1]], $result);
})->with('collections');

test('intersectNull', function (string $collection) {
    $c = new $collection(['id' => 1, 'first_word' => 'Hello']);
    $this->assertEquals([], $c->intersect(null)->all());
})->with('collections');

test('intersectCollection', function (string $collection) {
    $c = new $collection(['id' => 1, 'first_word' => 'Hello']);
    $this->assertEquals(['first_word' => 'Hello'], $c->intersect(new $collection(['first_world' => 'Hello', 'last_word' => 'World']))->all());
})->with('collections');

test('intersectUsingWithNull', function (string $collection) {
    $collect = new $collection(['green', 'brown', 'blue']);

    $this->assertEquals([], $collect->intersectUsing(null, 'strcasecmp')->all());
})->with('collections');

test('intersectUsingCollection', function (string $collection) {
    $collect = new $collection(['green', 'brown', 'blue']);

    $this->assertEquals(['green', 'brown'], $collect->intersectUsing(new $collection(['GREEN', 'brown', 'yellow']), 'strcasecmp')->all());
})->with('collections');

test('intersectAssocWithNull', function (string $collection) {
    $array1 = new $collection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);

    $this->assertEquals([], $array1->intersectAssoc(null)->all());
})->with('collections');

test('intersectAssocCollection', function (string $collection) {
    $array1 = new $collection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);
    $array2 = new $collection(['a' => 'green', 'b' => 'yellow', 'blue', 'red']);

    $this->assertEquals(['a' => 'green'], $array1->intersectAssoc($array2)->all());
})->with('collections');

test('intersectAssocUsingWithNull', function (string $collection) {
    $array1 = new $collection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);

    $this->assertEquals([], $array1->intersectAssocUsing(null, 'strcasecmp')->all());
})->with('collections');

test('intersectAssocUsingCollection', function (string $collection) {
    $array1 = new $collection(['a' => 'green', 'b' => 'brown', 'c' => 'blue', 'red']);
    $array2 = new $collection(['a' => 'GREEN', 'B' => 'brown', 'yellow', 'red']);

    $this->assertEquals(['b' => 'brown'], $array1->intersectAssocUsing($array2, 'strcasecmp')->all());
})->with('collections');

test('intersectByKeysNull', function (string $collection) {
    $c = new $collection(['name' => 'Mateus', 'age' => 18]);
    $this->assertEquals([], $c->intersectByKeys(null)->all());
})->with('collections');

test('intersectByKeys', function (string $collection) {
    $c = new $collection(['name' => 'Mateus', 'age' => 18]);
    $this->assertEquals(['name' => 'Mateus'], $c->intersectByKeys(new $collection(['name' => 'Mateus', 'surname' => 'Guimaraes']))->all());

    $c = new $collection(['name' => 'taylor', 'family' => 'otwell', 'age' => 26]);
    $this->assertEquals(['name' => 'taylor', 'family' => 'otwell'], $c->intersectByKeys(new $collection(['height' => 180, 'name' => 'amir', 'family' => 'moharami']))->all());
})->with('collections');

test('unique', function (string $collection) {
    $c = new $collection(['Hello', 'World', 'World']);
    $this->assertEquals(['Hello', 'World'], $c->unique()->all());

    $c = new $collection([[1, 2], [1, 2], [2, 3], [3, 4], [2, 3]]);
    $this->assertEquals([[1, 2], [2, 3], [3, 4]], $c->unique()->values()->all());
})->with('collections');

test('uniqueWithCallback', function (string $collection) {
    $c = new $collection([
        1 => ['id' => 1, 'first' => 'Taylor', 'last' => 'Otwell'],
        2 => ['id' => 2, 'first' => 'Taylor', 'last' => 'Otwell'],
        3 => ['id' => 3, 'first' => 'Abigail', 'last' => 'Otwell'],
        4 => ['id' => 4, 'first' => 'Abigail', 'last' => 'Otwell'],
        5 => ['id' => 5, 'first' => 'Taylor', 'last' => 'Swift'],
        6 => ['id' => 6, 'first' => 'Taylor', 'last' => 'Swift'],
    ]);

    $this->assertEquals([
        1 => ['id' => 1, 'first' => 'Taylor', 'last' => 'Otwell'],
        3 => ['id' => 3, 'first' => 'Abigail', 'last' => 'Otwell'],
    ], $c->unique('first')->all());

    $this->assertEquals([
        1 => ['id' => 1, 'first' => 'Taylor', 'last' => 'Otwell'],
        3 => ['id' => 3, 'first' => 'Abigail', 'last' => 'Otwell'],
        5 => ['id' => 5, 'first' => 'Taylor', 'last' => 'Swift'],
    ], $c->unique(function ($item) {
        return $item['first'].$item['last'];
    })->all());

    $this->assertEquals([
        1 => ['id' => 1, 'first' => 'Taylor', 'last' => 'Otwell'],
        2 => ['id' => 2, 'first' => 'Taylor', 'last' => 'Otwell'],
    ], $c->unique(function ($item, $key) {
        return $key % 2;
    })->all());
})->with('collections');

test('uniqueStrict', function (string $collection) {
    $c = new $collection([
        [
            'id' => '0',
            'name' => 'zero',
        ],
        [
            'id' => '00',
            'name' => 'double zero',
        ],
        [
            'id' => '0',
            'name' => 'again zero',
        ],
    ]);

    $this->assertEquals([
        [
            'id' => '0',
            'name' => 'zero',
        ],
        [
            'id' => '00',
            'name' => 'double zero',
        ],
    ], $c->uniqueStrict('id')->all());
})->with('collections');
