<?php

/**
 * Ported from Illuminate\Tests\Support\SupportCollectionTest (map,
 * mapSpread, flatMap, mapToDictionary, mapToGroups, mapWithKeys, mapInto,
 * nth, split/splitIn exceptions, transform, groupBy, keyBy).
 */

use Tests\Collections\Fixtures\GroupBySorters;
use Tests\Collections\Fixtures\TestCollectionMapIntoObject;
use Tests\NutsAndBolts\TestBackedEnum;
use Tests\NutsAndBolts\TestEnum;
use Tests\NutsAndBolts\TestStringBackedEnum;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Stringable;
use Voyager\NutsAndBolts\HtmlString;

test('map', function (string $collection) {
    $data = new $collection([1, 2, 3]);
    $mapped = $data->map(function ($item, $key) {
        return $item * 2;
    });
    $this->assertEquals([2, 4, 6], $mapped->all());
    $this->assertEquals([1, 2, 3], $data->all());

    $data = new $collection(['first' => 'taylor', 'last' => 'otwell']);
    $data = $data->map(function ($item, $key) {
        return $key.'-'.strrev($item);
    });
    $this->assertEquals(['first' => 'first-rolyat', 'last' => 'last-llewto'], $data->all());
})->with('collections');

test('mapSpread', function (string $collection) {
    $c = new $collection([[1, 'a'], [2, 'b']]);

    $result = $c->mapSpread(function ($number, $character) {
        return "{$number}-{$character}";
    });
    $this->assertEquals(['1-a', '2-b'], $result->all());

    $result = $c->mapSpread(function ($number, $character, $key) {
        return "{$number}-{$character}-{$key}";
    });
    $this->assertEquals(['1-a-0', '2-b-1'], $result->all());

    $c = new $collection([new Collection([1, 'a']), new Collection([2, 'b'])]);
    $result = $c->mapSpread(function ($number, $character, $key) {
        return "{$number}-{$character}-{$key}";
    });
    $this->assertEquals(['1-a-0', '2-b-1'], $result->all());
})->with('collections');

test('flatMap', function (string $collection) {
    $data = new $collection([
        ['name' => 'taylor', 'hobbies' => ['programming', 'basketball']],
        ['name' => 'adam', 'hobbies' => ['music', 'powerlifting']],
    ]);
    $data = $data->flatMap(function ($person) {
        return $person['hobbies'];
    });
    $this->assertEquals(['programming', 'basketball', 'music', 'powerlifting'], $data->all());
})->with('collections');

test('mapToDictionary', function (string $collection) {
    $data = new $collection([
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'B'],
        ['id' => 3, 'name' => 'C'],
        ['id' => 4, 'name' => 'B'],
    ]);

    $groups = $data->mapToDictionary(function ($item, $key) {
        return [$item['name'] => $item['id']];
    });

    $this->assertInstanceOf($collection, $groups);
    $this->assertEquals(['A' => [1], 'B' => [2, 4], 'C' => [3]], $groups->toArray());
    $this->assertIsArray($groups->get('A'));
})->with('collections');

test('mapToDictionaryWithNumericKeys', function (string $collection) {
    $data = new $collection([1, 2, 3, 2, 1]);

    $groups = $data->mapToDictionary(function ($item, $key) {
        return [$item => $key];
    });

    $this->assertEquals([1 => [0, 4], 2 => [1, 3], 3 => [2]], $groups->toArray());
})->with('collections');

test('mapToGroups', function (string $collection) {
    $data = new $collection([
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'B'],
        ['id' => 3, 'name' => 'C'],
        ['id' => 4, 'name' => 'B'],
    ]);

    $groups = $data->mapToGroups(function ($item, $key) {
        return [$item['name'] => $item['id']];
    });

    $this->assertInstanceOf($collection, $groups);
    $this->assertEquals(['A' => [1], 'B' => [2, 4], 'C' => [3]], $groups->toArray());
    $this->assertInstanceOf($collection, $groups->get('A'));
})->with('collections');

test('mapToGroupsWithNumericKeys', function (string $collection) {
    $data = new $collection([1, 2, 3, 2, 1]);

    $groups = $data->mapToGroups(function ($item, $key) {
        return [$item => $key];
    });

    $this->assertEquals([1 => [0, 4], 2 => [1, 3], 3 => [2]], $groups->toArray());
    $this->assertEquals([1, 2, 3, 2, 1], $data->all());
})->with('collections');

test('mapWithKeys', function (string $collection) {
    $data = new $collection([
        ['name' => 'Blastoise', 'type' => 'Water', 'idx' => 9],
        ['name' => 'Charmander', 'type' => 'Fire', 'idx' => 4],
        ['name' => 'Dragonair', 'type' => 'Dragon', 'idx' => 148],
    ]);
    $data = $data->mapWithKeys(function ($pokemon) {
        return [$pokemon['name'] => $pokemon['type']];
    });
    $this->assertEquals(
        ['Blastoise' => 'Water', 'Charmander' => 'Fire', 'Dragonair' => 'Dragon'],
        $data->all()
    );
})->with('collections');

test('mapWithKeysIntegerKeys', function (string $collection) {
    $data = new $collection([
        ['id' => 1, 'name' => 'A'],
        ['id' => 3, 'name' => 'B'],
        ['id' => 2, 'name' => 'C'],
    ]);
    $data = $data->mapWithKeys(function ($item) {
        return [$item['id'] => $item];
    });
    $this->assertSame(
        [1, 3, 2],
        $data->keys()->all()
    );
})->with('collections');

test('mapWithKeysMultipleRows', function (string $collection) {
    $data = new $collection([
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'B'],
        ['id' => 3, 'name' => 'C'],
    ]);
    $data = $data->mapWithKeys(function ($item) {
        return [$item['id'] => $item['name'], $item['name'] => $item['id']];
    });
    $this->assertSame(
        [
            1 => 'A',
            'A' => 1,
            2 => 'B',
            'B' => 2,
            3 => 'C',
            'C' => 3,
        ],
        $data->all()
    );
})->with('collections');

test('mapWithKeysCallbackKey', function (string $collection) {
    $data = new $collection([
        3 => ['id' => 1, 'name' => 'A'],
        5 => ['id' => 3, 'name' => 'B'],
        4 => ['id' => 2, 'name' => 'C'],
    ]);
    $data = $data->mapWithKeys(function ($item, $key) {
        return [$key => $item['id']];
    });
    $this->assertSame(
        [3, 5, 4],
        $data->keys()->all()
    );
})->with('collections');

test('mapInto', function (string $collection) {
    $data = new $collection([
        'first', 'second',
    ]);

    $data = $data->mapInto(TestCollectionMapIntoObject::class);

    $this->assertSame('first', $data->get(0)->value);
    $this->assertSame('second', $data->get(1)->value);
})->with('collections');

test('mapIntoWithIntBackedEnums', function (string $collection) {
    $data = new $collection([
        1, 2,
    ]);

    $data = $data->mapInto(TestBackedEnum::class);

    $this->assertSame(TestBackedEnum::A, $data->get(0));
    $this->assertSame(TestBackedEnum::B, $data->get(1));
})->with('collections');

test('mapIntoWithStringBackedEnums', function (string $collection) {
    $data = new $collection([
        'A', 'B',
    ]);

    $data = $data->mapInto(TestStringBackedEnum::class);

    $this->assertSame(TestStringBackedEnum::A, $data->get(0));
    $this->assertSame(TestStringBackedEnum::B, $data->get(1));
})->with('collections');

test('nth', function (string $collection) {
    $data = new $collection([
        6 => 'a',
        4 => 'b',
        7 => 'c',
        1 => 'd',
        5 => 'e',
        3 => 'f',
    ]);

    $this->assertEquals(['a', 'e'], $data->nth(4)->all());
    $this->assertEquals(['b', 'f'], $data->nth(4, 1)->all());
    $this->assertEquals(['c'], $data->nth(4, 2)->all());
    $this->assertEquals(['d'], $data->nth(4, 3)->all());
    $this->assertEquals(['c', 'e'], $data->nth(2, 2)->all());
    $this->assertEquals(['c', 'd', 'e', 'f'], $data->nth(1, 2)->all());
    $this->assertEquals(['c', 'd', 'e', 'f'], $data->nth(1, 2)->all());
    $this->assertEquals(['e', 'f'], $data->nth(1, -2)->all());
    $this->assertEquals(['c', 'e'], $data->nth(2, -4)->all());
    $this->assertEquals(['e'], $data->nth(4, -2)->all());
    $this->assertEquals(['e'], $data->nth(2, -2)->all());
})->with('collections');

test('nthThrowsExceptionForInvalidStep', function (string $collection) {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Step value must be at least 1.');

    (new $collection([1, 2, 3]))->nth(0)->all();
})->with('collections');

test('nthThrowsExceptionForNegativeStep', function (string $collection) {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Step value must be at least 1.');

    (new $collection([1, 2, 3]))->nth(-1)->all();
})->with('collections');

test('splitThrowsExceptionForInvalidNumberOfGroups', function (string $collection) {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Number of groups must be at least 1.');

    (new $collection([1, 2, 3]))->split(0);
})->with('collections');

test('splitThrowsExceptionForNegativeNumberOfGroups', function (string $collection) {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Number of groups must be at least 1.');

    (new $collection([1, 2, 3]))->split(-1);
})->with('collections');

test('splitInThrowsExceptionForInvalidNumberOfGroups', function (string $collection) {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Number of groups must be at least 1.');

    (new $collection([1, 2, 3]))->splitIn(0);
})->with('collections');

test('splitInThrowsExceptionForNegativeNumberOfGroups', function (string $collection) {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Number of groups must be at least 1.');

    (new $collection([1, 2, 3]))->splitIn(-1);
})->with('collections');

test('mapWithKeysOverwritingKeys', function (string $collection) {
    $data = new $collection([
        ['id' => 1, 'name' => 'A'],
        ['id' => 2, 'name' => 'B'],
        ['id' => 1, 'name' => 'C'],
    ]);
    $data = $data->mapWithKeys(function ($item) {
        return [$item['id'] => $item['name']];
    });
    $this->assertSame(
        [
            1 => 'C',
            2 => 'B',
        ],
        $data->all()
    );
})->with('collections');

test('transform', function () {
    $data = new Collection(['first' => 'taylor', 'last' => 'otwell']);
    $data->transform(function ($item, $key) {
        return $key.'-'.strrev($item);
    });
    $this->assertEquals(['first' => 'first-rolyat', 'last' => 'last-llewto'], $data->all());
});

test('groupByAttribute', function (string $collection) {
    $data = new $collection([['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1'], ['rating' => 2, 'url' => '2']]);

    $result = $data->groupBy('rating');
    $this->assertEquals([1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']], 2 => [['rating' => 2, 'url' => '2']]], $result->toArray());

    $result = $data->groupBy('url');
    $this->assertEquals([1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']], 2 => [['rating' => 2, 'url' => '2']]], $result->toArray());
})->with('collections');

test('groupByAttributeWithStringableKey', function (string $collection) {
    $data = new $collection($payload = [
        ['name' => new Stringable('Laravel'), 'url' => '1'],
        ['name' => new HtmlString('Laravel'), 'url' => '1'],
        ['name' => new class()
        {
            public function __toString()
            {
                return 'Framework';
            }
        }, 'url' => '2', ],
    ]);

    $result = $data->groupBy('name');
    $this->assertEquals(['Laravel' => [$payload[0], $payload[1]], 'Framework' => [$payload[2]]], $result->toArray());

    $result = $data->groupBy('url');
    $this->assertEquals(['1' => [$payload[0], $payload[1]], '2' => [$payload[2]]], $result->toArray());
})->with('collections');

test('groupByAttributeWithEnumKey', function (string $collection) {
    $data = new $collection($payload = [
        ['name' => TestEnum::A, 'url' => '1'],
        ['name' => TestBackedEnum::A, 'url' => '1'],
        ['name' => TestStringBackedEnum::A, 'url' => '2'],
    ]);

    $result = $data->groupBy('name');
    $this->assertEquals(['A' => [$payload[0], $payload[2]], '1' => [$payload[1]]], $result->toArray());

    $result = $data->groupBy('url');
    $this->assertEquals(['1' => [$payload[0], $payload[1]], '2' => [$payload[2]]], $result->toArray());
})->with('collections');

test('groupByCallable', function (string $collection) {
    $data = new $collection([['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1'], ['rating' => 2, 'url' => '2']]);

    $result = $data->groupBy([GroupBySorters::class, 'sortByRating']);
    $this->assertEquals([1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']], 2 => [['rating' => 2, 'url' => '2']]], $result->toArray());

    $result = $data->groupBy([GroupBySorters::class, 'sortByUrl']);
    $this->assertEquals([1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']], 2 => [['rating' => 2, 'url' => '2']]], $result->toArray());
})->with('collections');

test('groupByAttributeWithBackedEnumKey', function (string $collection) {
    $data = new $collection([
        ['rating' => TestBackedEnum::A, 'url' => '1'],
        ['rating' => TestBackedEnum::B, 'url' => '1'],
    ]);

    $result = $data->groupBy('rating');
    $this->assertEquals([TestBackedEnum::A->value => [['rating' => TestBackedEnum::A, 'url' => '1']], TestBackedEnum::B->value => [['rating' => TestBackedEnum::B, 'url' => '1']]], $result->toArray());
})->with('collections');

test('groupByAttributePreservingKeys', function (string $collection) {
    $data = new $collection([10 => ['rating' => 1, 'url' => '1'],  20 => ['rating' => 1, 'url' => '1'],  30 => ['rating' => 2, 'url' => '2']]);

    $result = $data->groupBy('rating', true);

    $expected_result = [
        1 => [10 => ['rating' => 1, 'url' => '1'], 20 => ['rating' => 1, 'url' => '1']],
        2 => [30 => ['rating' => 2, 'url' => '2']],
    ];

    $this->assertEquals($expected_result, $result->toArray());
})->with('collections');

test('groupByClosureWhereItemsHaveSingleGroup', function (string $collection) {
    $data = new $collection([['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1'], ['rating' => 2, 'url' => '2']]);

    $result = $data->groupBy(function ($item) {
        return $item['rating'];
    });

    $this->assertEquals([1 => [['rating' => 1, 'url' => '1'], ['rating' => 1, 'url' => '1']], 2 => [['rating' => 2, 'url' => '2']]], $result->toArray());
})->with('collections');

test('groupByClosureWhereItemsHaveSingleGroupPreservingKeys', function (string $collection) {
    $data = new $collection([10 => ['rating' => 1, 'url' => '1'], 20 => ['rating' => 1, 'url' => '1'], 30 => ['rating' => 2, 'url' => '2']]);

    $result = $data->groupBy(function ($item) {
        return $item['rating'];
    }, true);

    $expected_result = [
        1 => [10 => ['rating' => 1, 'url' => '1'], 20 => ['rating' => 1, 'url' => '1']],
        2 => [30 => ['rating' => 2, 'url' => '2']],
    ];

    $this->assertEquals($expected_result, $result->toArray());
})->with('collections');

test('groupByClosureWhereItemsHaveMultipleGroups', function (string $collection) {
    $data = new $collection([
        ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
        ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
        ['user' => 3, 'roles' => ['Role_1']],
    ]);

    $result = $data->groupBy(function ($item) {
        return $item['roles'];
    });

    $expected_result = [
        'Role_1' => [
            ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
            ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
            ['user' => 3, 'roles' => ['Role_1']],
        ],
        'Role_2' => [
            ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
        ],
        'Role_3' => [
            ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
        ],
    ];

    $this->assertEquals($expected_result, $result->toArray());
})->with('collections');

test('groupByClosureWhereItemsHaveMultipleGroupsPreservingKeys', function (string $collection) {
    $data = new $collection([
        10 => ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
        20 => ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
        30 => ['user' => 3, 'roles' => ['Role_1']],
    ]);

    $result = $data->groupBy(function ($item) {
        return $item['roles'];
    }, true);

    $expected_result = [
        'Role_1' => [
            10 => ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
            20 => ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
            30 => ['user' => 3, 'roles' => ['Role_1']],
        ],
        'Role_2' => [
            20 => ['user' => 2, 'roles' => ['Role_1', 'Role_2']],
        ],
        'Role_3' => [
            10 => ['user' => 1, 'roles' => ['Role_1', 'Role_3']],
        ],
    ];

    $this->assertEquals($expected_result, $result->toArray());
})->with('collections');

test('groupByMultiLevelAndClosurePreservingKeys', function (string $collection) {
    $data = new $collection([
        10 => ['user' => 1, 'skilllevel' => 1, 'roles' => ['Role_1', 'Role_3']],
        20 => ['user' => 2, 'skilllevel' => 1, 'roles' => ['Role_1', 'Role_2']],
        30 => ['user' => 3, 'skilllevel' => 2, 'roles' => ['Role_1']],
        40 => ['user' => 4, 'skilllevel' => 2, 'roles' => ['Role_2']],
    ]);

    $result = $data->groupBy([
        'skilllevel',
        function ($item) {
            return $item['roles'];
        },
    ], true);

    $expected_result = [
        1 => [
            'Role_1' => [
                10 => ['user' => 1, 'skilllevel' => 1, 'roles' => ['Role_1', 'Role_3']],
                20 => ['user' => 2, 'skilllevel' => 1, 'roles' => ['Role_1', 'Role_2']],
            ],
            'Role_3' => [
                10 => ['user' => 1, 'skilllevel' => 1, 'roles' => ['Role_1', 'Role_3']],
            ],
            'Role_2' => [
                20 => ['user' => 2, 'skilllevel' => 1, 'roles' => ['Role_1', 'Role_2']],
            ],
        ],
        2 => [
            'Role_1' => [
                30 => ['user' => 3, 'skilllevel' => 2, 'roles' => ['Role_1']],
            ],
            'Role_2' => [
                40 => ['user' => 4, 'skilllevel' => 2, 'roles' => ['Role_2']],
            ],
        ],
    ];

    $this->assertEquals($expected_result, $result->toArray());
})->with('collections');

test('keyByAttribute', function (string $collection) {
    $data = new $collection([['rating' => 1, 'name' => '1'], ['rating' => 2, 'name' => '2'], ['rating' => 3, 'name' => '3']]);

    $result = $data->keyBy('rating');
    $this->assertEquals([1 => ['rating' => 1, 'name' => '1'], 2 => ['rating' => 2, 'name' => '2'], 3 => ['rating' => 3, 'name' => '3']], $result->all());

    $result = $data->keyBy(function ($item) {
        return $item['rating'] * 2;
    });
    $this->assertEquals([2 => ['rating' => 1, 'name' => '1'], 4 => ['rating' => 2, 'name' => '2'], 6 => ['rating' => 3, 'name' => '3']], $result->all());
})->with('collections');

test('keyByClosure', function (string $collection) {
    $data = new $collection([
        ['firstname' => 'Taylor', 'lastname' => 'Otwell', 'locale' => 'US'],
        ['firstname' => 'Lucas', 'lastname' => 'Michot', 'locale' => 'FR'],
    ]);
    $result = $data->keyBy(function ($item, $key) {
        return strtolower($key.'-'.$item['firstname'].$item['lastname']);
    });
    $this->assertEquals([
        '0-taylorotwell' => ['firstname' => 'Taylor', 'lastname' => 'Otwell', 'locale' => 'US'],
        '1-lucasmichot' => ['firstname' => 'Lucas', 'lastname' => 'Michot', 'locale' => 'FR'],
    ], $result->all());
})->with('collections');

test('keyByObject', function (string $collection) {
    $data = new $collection([
        ['firstname' => 'Taylor', 'lastname' => 'Otwell', 'locale' => 'US'],
        ['firstname' => 'Lucas', 'lastname' => 'Michot', 'locale' => 'FR'],
    ]);
    $result = $data->keyBy(function ($item, $key) use ($collection) {
        return new $collection([$key, $item['firstname'], $item['lastname']]);
    });
    $this->assertEquals([
        '[0,"Taylor","Otwell"]' => ['firstname' => 'Taylor', 'lastname' => 'Otwell', 'locale' => 'US'],
        '[1,"Lucas","Michot"]' => ['firstname' => 'Lucas', 'lastname' => 'Michot', 'locale' => 'FR'],
    ], $result->all());
})->with('collections');
