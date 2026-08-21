<?php

use PHPUnit\Framework\AssertionFailedError;
use Tests\Testing\Fluent\BackedEnum;
use Tests\Testing\Stubs\ArrayableStubObject;
use Voyager\NutsAndBolts\Collection;
use Voyager\Testing\Fluent\AssertableJson;

test('assert has', function () {
    $assert = AssertableJson::fromArray([
        'prop' => 'value',
    ]);

    $assert->has('prop');
});

test('assert has fails when prop missing', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->has('prop');
})->throws(AssertionFailedError::class, 'Property [prop] does not exist.');

test('assert has nested prop', function () {
    $assert = AssertableJson::fromArray([
        'example' => [
            'nested' => 'nested-value',
        ],
    ]);

    $assert->has('example.nested');
});

test('assert has fails when nested prop missing', function () {
    $assert = AssertableJson::fromArray([
        'example' => [
            'nested' => 'nested-value',
        ],
    ]);

    $assert->has('example.another');
})->throws(AssertionFailedError::class, 'Property [example.another] does not exist.');

test('assert has count items in prop', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $assert->has('bar', 2);
});

test('assert has count fails when amount of items does not match', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $assert->has('bar', 1);
})->throws(AssertionFailedError::class, 'Property [bar] does not have the expected size.');

test('assert has count fails when prop missing', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $assert->has('baz', 1);
})->throws(AssertionFailedError::class, 'Property [baz] does not exist.');

test('assert has fails when second argument unsupported type', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'baz',
    ]);

    $assert->has('bar', 'invalid');
})->throws(TypeError::class);

test('assert has only counts', function () {
    $assert = AssertableJson::fromArray([
        'foo',
        'bar',
        'baz',
    ]);

    $assert->has(3);
});

test('assert has only count fails', function () {
    $assert = AssertableJson::fromArray([
        'foo',
        'bar',
        'baz',
    ]);

    $assert->has(2);
})->throws(AssertionFailedError::class, 'Root level does not have the expected size.');

test('assert has only count fails scoped', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $assert->has('bar', function ($bar) {
        $bar->has(3);
    });
})->throws(AssertionFailedError::class, 'Property [bar] does not have the expected size.');

test('assert has with where not does not fail', function () {
    $assert = AssertableJson::fromArray([
        'data' => [
            [
                'id' => 1,
                'name' => 'Taylor',
            ],
            [
                'id' => 2,
                'name' => 'Nuno',
            ],
        ],
    ]);

    $assert->has('data', function ($bar) {
        $bar->has(2)
            ->each(fn ($json) => $json->whereNot('id', 3)->etc());
    });
});

test('assert has with where not fails', function () {
    $assert = AssertableJson::fromArray([
        'data' => [
            [
                'id' => 1,
                'name' => 'Taylor',
            ],
            [
                'id' => 2,
                'name' => 'Mateus',
            ],
        ],
    ]);

    $assert->has('data', function ($bar) {
        $bar->has(2)
            ->each(fn ($json) => $json->whereNot('id', 2)->etc());
    });
})->throws(AssertionFailedError::class, 'Property [data.1.id] contains a value that should be missing: [id, 2]');

test('assert has with where not does not fail closure', function () {
    $assert = AssertableJson::fromArray([
        'data' => [
            [
                'id' => 1,
                'name' => 'Taylor',
            ],
            [
                'id' => 2,
                'name' => 'Mateus',
            ],
        ],
    ]);

    $assert->has('data', function ($bar) {
        $bar->has(2)
            ->each(fn ($json) => $json->whereNot('id', fn ($value) => $value === 3)->etc());
    });
});

test('assert has with where not fails closure', function () {
    $assert = AssertableJson::fromArray([
        'data' => [
            [
                'id' => 1,
                'name' => 'Taylor',
            ],
            [
                'id' => 2,
                'name' => 'Mateus',
            ],
        ],
    ]);

    $assert->has('data', function ($bar) {
        $bar->has(2)
            ->each(fn ($json) => $json->whereNot('id', fn ($value) => $value === 2)->etc());
    });
})->throws(AssertionFailedError::class, 'Property [data.1.id] was marked as invalid using a closure.');

test('assert count', function () {
    $assert = AssertableJson::fromArray([
        'foo',
        'bar',
        'baz',
    ]);

    $assert->count(3);
});

test('assert count fails', function () {
    $assert = AssertableJson::fromArray([
        'foo',
        'bar',
        'baz',
    ]);

    $assert->count(2);
})->throws(AssertionFailedError::class, 'Root level does not have the expected size.');

test('assert count fails scoped', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $assert->has('bar', function ($bar) {
        $bar->count(3);
    });
})->throws(AssertionFailedError::class, 'Property [bar] does not have the expected size.');

test('assert count between', function () {
    $assert = AssertableJson::fromArray([
        'foo',
        'bar',
        'baz',
    ]);

    $assert->countBetween(1, 3);
});

test('assert count between fails', function () {
    $assert = AssertableJson::fromArray([
        'foo',
        'bar',
        'baz',
    ]);

    $assert->countBetween(1, 2);
})->throws(AssertionFailedError::class, 'Root level size is not less than or equal to [2].');

test('assert count between lowest value fails', function () {
    $assert = AssertableJson::fromArray([
        'foo',
        'bar',
        'baz',
    ]);

    $assert->countBetween(4, 3);
})->throws(AssertionFailedError::class, 'Root level size is not greater than or equal to [4].');

test('assert count between fails scoped', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
            'foo' => 'value',
        ],
    ]);

    $assert->has('bar', function (AssertableJson $bar) {
        $bar->countBetween(1, 2);
    });
})->throws(AssertionFailedError::class, 'Property [bar] size is not less than or equal to [2].');

test('assert missing', function () {
    $assert = AssertableJson::fromArray([
        'foo' => [
            'bar' => true,
        ],
    ]);

    $assert->missing('foo.baz');
});

test('assert missing fails when prop exists', function () {
    $assert = AssertableJson::fromArray([
        'prop' => 'value',
        'foo' => [
            'bar' => true,
        ],
    ]);

    $assert->missing('foo.bar');
})->throws(AssertionFailedError::class, 'Property [foo.bar] was found while it was expected to be missing.');

test('assert missing all', function () {
    $assert = AssertableJson::fromArray([
        'baz' => 'foo',
    ]);

    $assert->missingAll([
        'foo',
        'bar',
    ]);
});

test('assert missing all fails when at least one prop exists', function () {
    $assert = AssertableJson::fromArray([
        'baz' => 'foo',
    ]);

    $assert->missingAll([
        'bar',
        'baz',
    ]);
})->throws(AssertionFailedError::class, 'Property [baz] was found while it was expected to be missing.');

test('assert missing all accepts multiple arguments instead of array', function () {
    $assert = AssertableJson::fromArray([
        'baz' => 'foo',
    ]);

    $assert->missingAll('foo', 'bar');

    $assert->missingAll('bar', 'baz');
})->throws(AssertionFailedError::class, 'Property [baz] was found while it was expected to be missing.');

test('assert where matches value', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->where('bar', 'value');
});

test('assert where fails when does not match value', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->where('bar', 'invalid');
})->throws(AssertionFailedError::class, 'Property [bar] does not match the expected value.');

test('assert where fails when missing', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->where('baz', 'invalid');
})->throws(AssertionFailedError::class, 'Property [baz] does not exist.');

test('assert where fails when matching loosely', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 1,
    ]);

    $assert->where('bar', true);
})->throws(AssertionFailedError::class, 'Property [bar] does not match the expected value.');

test('assert where using closure', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'baz',
    ]);

    $assert->where('bar', function ($value) {
        return $value === 'baz';
    });
});

test('assert where fails when does not match value using closure', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'baz',
    ]);

    $assert->where('bar', function ($value) {
        return $value === 'invalid';
    });
})->throws(AssertionFailedError::class, 'Property [bar] was marked as invalid using a closure.');

test('assert where closure array values are automatically casted to collections', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'foo',
            'example' => 'value',
        ],
    ]);

    $assert->where('bar', function ($value) {
        expect($value)->toBeInstanceOf(Collection::class);

        return $value->count() === 2;
    });
});

test('assert where matches value using arrayable', function () {
    $stub = ArrayableStubObject::make(['foo' => 'bar']);

    $assert = AssertableJson::fromArray([
        'bar' => $stub->toArray(),
    ]);

    $assert->where('bar', $stub);
});

test('assert where matches value using arrayable when sorted differently', function () {
    $assert = AssertableJson::fromArray([
        'data' => [
            'status' => 200,
            'user' => [
                'id' => 1,
                'name' => 'Taylor',
            ],
        ],
    ]);

    $assert->where('data', [
        'user' => [
            'name' => 'Taylor',
            'id' => 1,
        ],
        'status' => 200,
    ]);
});

test('assert where fails when does not match value using arrayable', function () {
    $assert = AssertableJson::fromArray([
        'bar' => ['id' => 1, 'name' => 'Example'],
        'baz' => [
            'id' => 1,
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'email_verified_at' => '2021-01-22T10:34:42.000000Z',
            'created_at' => '2021-01-22T10:34:42.000000Z',
            'updated_at' => '2021-01-22T10:34:42.000000Z',
        ],
    ]);

    $assert
        ->where('bar', ArrayableStubObject::make(['name' => 'Example', 'id' => 1]))
        ->where('baz', [
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'id' => 1,
            'email_verified_at' => '2021-01-22T10:34:42.000000Z',
            'updated_at' => '2021-01-22T10:34:42.000000Z',
            'created_at' => '2021-01-22T10:34:42.000000Z',
        ]);
});

test('assert where using backed enum', function () {
    $assert = AssertableJson::fromArray([
        'bar' => BackedEnum::test->value,
    ]);

    $assert->where('bar', BackedEnum::test);

    $assert = AssertableJson::fromArray([
        'bar' => BackedEnum::test_empty->value,
    ]);

    $assert->where('bar', BackedEnum::test_empty);
});

test('assert where fails using backed enum', function () {
    $assert = AssertableJson::fromArray([
        'bar' => BackedEnum::test->value,
    ]);

    $assert->where('bar', BackedEnum::test_empty);
})->throws(AssertionFailedError::class, 'Property [bar] does not match the expected value.');

test('assert where null matches value', function () {
    $assert = AssertableJson::fromArray([
        'bar' => null,
    ]);

    $assert->whereNull('bar');
});

test('assert where null fails when not null', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->whereNull('bar');
})->throws(AssertionFailedError::class, 'Property [bar] should be null.');

test('assert where null fails when missing', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->whereNull('baz');
})->throws(AssertionFailedError::class, 'Property [baz] does not exist.');

test('assert where not null matches value', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->whereNotNull('bar');
});

test('assert where not null fails when null', function () {
    $assert = AssertableJson::fromArray([
        'bar' => null,
    ]);

    $assert->whereNotNull('bar');
})->throws(AssertionFailedError::class, 'Property [bar] should not be null.');

test('assert where not null fails when missing', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->whereNotNull('baz');
})->throws(AssertionFailedError::class, 'Property [baz] does not exist.');

describe('where contains', function () {
    test('fails with empty value', function () {
        $assert = AssertableJson::fromArray([]);

        $assert->whereContains('foo', ['1']);
    })->throws(AssertionFailedError::class, 'Property [foo] does not contain [1].');

    test('fails with missing value', function () {
        $assert = AssertableJson::fromArray([
            'foo' => ['bar', 'baz'],
        ]);

        $assert->whereContains('foo', ['bar', 'baz', 'invalid']);
    })->throws(AssertionFailedError::class, 'Property [foo] does not contain [invalid].');

    test('fails with missing nested value', function () {
        $assert = AssertableJson::fromArray([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
            ['id' => 4],
        ]);

        $assert->whereContains('id', [1, 2, 3, 4, 5]);
    })->throws(AssertionFailedError::class, 'Property [id] does not contain [5].');

    test('fails when does not match type', function () {
        $assert = AssertableJson::fromArray([
            'foo' => [1, 2, 3, 4],
        ]);

        $assert->whereContains('foo', ['1']);
    })->throws(AssertionFailedError::class, 'Property [foo] does not contain [1].');

    test('fails when does not satisfy closure', function () {
        $assert = AssertableJson::fromArray([
            'foo' => [1, 2, 3, 4],
        ]);

        $assert->whereContains('foo', [function ($actual) {
            return $actual === 5;
        }]);
    })->throws(AssertionFailedError::class, 'Property [foo] does not contain a value that passes the truth test within the given closure.');

    test('fails when having expected value but does not satisfy closure', function () {
        $assert = AssertableJson::fromArray([
            'foo' => [1, 2, 3, 4],
        ]);

        $assert->whereContains('foo', [1, function ($actual) {
            return $actual === 5;
        }]);
    })->throws(AssertionFailedError::class, 'Property [foo] does not contain a value that passes the truth test within the given closure.');

    test('fails when satisfies closure but does not have expected value', function () {
        $assert = AssertableJson::fromArray([
            'foo' => [1, 2, 3, 4],
        ]);

        $assert->whereContains('foo', [5, function ($actual) {
            return $actual === 1;
        }]);
    })->throws(AssertionFailedError::class, 'Property [foo] does not contain [5].');

    test('with nested value', function () {
        $assert = AssertableJson::fromArray([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
            ['id' => 4],
        ]);

        $assert->whereContains('id', 1);
        $assert->whereContains('id', [1, 2, 3, 4]);
        $assert->whereContains('id', [4, 3, 2, 1]);
    });

    test('with matching type', function () {
        $assert = AssertableJson::fromArray([
            'foo' => [1, 2, 3, 4],
        ]);

        $assert->whereContains('foo', 1);
        $assert->whereContains('foo', [1]);
    });

    test('with null value', function () {
        $assert = AssertableJson::fromArray([
            'foo' => null,
        ]);

        $assert->whereContains('foo', null);
        $assert->whereContains('foo', [null]);
    });

    test('with out of order matching type', function () {
        $assert = AssertableJson::fromArray([
            'foo' => [4, 1, 7, 3],
        ]);

        $assert->whereContains('foo', [1, 7, 4, 3]);
    });

    test('with out of order nested matching type', function () {
        $assert = AssertableJson::fromArray([
            ['bar' => 5],
            ['baz' => 4],
            ['zal' => 8],
        ]);

        $assert->whereContains('baz', 4);
    });

    test('with closure', function () {
        $assert = AssertableJson::fromArray([
            'foo' => [1, 2, 3, 4],
        ]);

        $assert->whereContains('foo', function ($actual) {
            return $actual % 3 === 0;
        });
    });

    test('with nested closure', function () {
        $assert = AssertableJson::fromArray([
            'foo' => 1,
            'bar' => 2,
            'baz' => 3,
        ]);

        $assert->whereContains('baz', function ($actual) {
            return $actual % 3 === 0;
        });
    });

    test('with multiple closure', function () {
        $assert = AssertableJson::fromArray([
            'foo' => [1, 2, 3, 4],
        ]);

        $assert->whereContains('foo', [
            function ($actual) {
                return $actual % 3 === 0;
            },
            function ($actual) {
                return $actual % 2 === 0;
            },
        ]);
    });

    test('with null expectation', function () {
        $assert = AssertableJson::fromArray([
            'foo' => 1,
        ]);

        $assert->whereContains('foo', null);
    });

    test('using backed enum', function () {
        $assert = AssertableJson::fromArray([
            'bar' => [BackedEnum::test->value],
        ]);

        $assert->whereContains('bar', BackedEnum::test);

        $assert = AssertableJson::fromArray([
            'bar' => [BackedEnum::test_empty->value],
        ]);

        $assert->whereContains('bar', BackedEnum::test_empty);
    });

    test('fails using backed enum', function () {
        $assert = AssertableJson::fromArray([
            'bar' => [BackedEnum::test_empty->value],
        ]);

        $assert->whereContains('bar', BackedEnum::test);
    })->throws(AssertionFailedError::class, 'Property [bar] does not contain [test].');
});

test('assert nested where matches value', function () {
    $assert = AssertableJson::fromArray([
        'example' => [
            'nested' => 'nested-value',
        ],
    ]);

    $assert->where('example.nested', 'nested-value');
});

test('assert nested where fails when does not match value', function () {
    $assert = AssertableJson::fromArray([
        'example' => [
            'nested' => 'nested-value',
        ],
    ]);

    $assert->where('example.nested', 'another-value');
})->throws(AssertionFailedError::class, 'Property [example.nested] does not match the expected value.');

test('assert nested where using backed enum', function () {
    $assert = AssertableJson::fromArray([
        'example' => [
            'nested' => BackedEnum::test->value,
        ],
    ]);

    $assert->where('example.nested', BackedEnum::test);
});

test('assert nested where fails using backed enum', function () {
    $assert = AssertableJson::fromArray([
        'example' => [
            'nested' => BackedEnum::test_empty->value,
        ],
    ]);

    $assert->where('example.nested', BackedEnum::test);
})->throws(AssertionFailedError::class, 'Property [example.nested] does not match the expected value.');

test('assert where does not match value', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->whereNot('bar', 'different_value');
});

test('assert where not fails when matching value', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->whereNot('bar', 'value');
})->throws(AssertionFailedError::class, 'Property [bar] contains a value that should be missing: [bar, value]');

test('assert where not fails when not missing', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->whereNot('baz', 'value');
})->throws(AssertionFailedError::class, 'Property [baz] does not exist.');

test('assert where not using closure', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'baz',
    ]);

    $assert->whereNot('bar', function ($value) {
        return $value === 'foo';
    });
});

test('assert where not fails when matches value using closure', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'baz',
    ]);

    $assert->whereNot('bar', function ($value) {
        return $value === 'baz';
    });
})->throws(AssertionFailedError::class, 'Property [bar] was marked as invalid using a closure.');

test('assert where not using backed enum', function () {
    $assert = AssertableJson::fromArray([
        'bar' => BackedEnum::test->value,
    ]);

    $assert->whereNot('bar', BackedEnum::test_empty);
});

test('assert where not fails using backed enum', function () {
    $assert = AssertableJson::fromArray([
        'bar' => BackedEnum::test->value,
    ]);

    $assert->whereNot('bar', BackedEnum::test);
})->throws(AssertionFailedError::class, 'Property [bar] contains a value that should be missing: [bar, test]');

test('scope', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $called = false;
    $assert->has('bar', function (AssertableJson $assert) use (&$called) {
        $called = true;
        $assert
            ->where('baz', 'example')
            ->where('prop', 'value');
    });

    expect($called)->toBeTrue();
});

test('scope fails when prop missing', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $assert->has('baz', function (AssertableJson $item) {
        $item->where('baz', 'example');
    });
})->throws(AssertionFailedError::class, 'Property [baz] does not exist.');

test('scope fails when prop single value', function () {
    $assert = AssertableJson::fromArray([
        'bar' => 'value',
    ]);

    $assert->has('bar', function (AssertableJson $item) {
        //
    });
})->throws(AssertionFailedError::class, 'Property [bar] is not scopeable.');

test('scope shorthand', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            ['key' => 'first'],
            ['key' => 'second'],
        ],
    ]);

    $called = false;
    $assert->has('bar', 2, function (AssertableJson $item) use (&$called) {
        $item->where('key', 'first');
        $called = true;
    });

    expect($called)->toBeTrue();
});

test('scope shorthand without count', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            ['key' => 'first'],
            ['key' => 'second'],
        ],
    ]);

    $called = false;
    $assert->has('bar', null, function (AssertableJson $item) use (&$called) {
        $item->where('key', 'first');
        $called = true;
    });

    expect($called)->toBeTrue();
});

test('scope shorthand fails when asserting zero items', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            ['key' => 'first'],
            ['key' => 'second'],
        ],
    ]);

    $assert->has('bar', 0, function (AssertableJson $item) {
        $item->where('key', 'first');
    });
})->throws(AssertionFailedError::class, 'Property [bar] does not have the expected size.');

test('scope shorthand fails when amount of items does not match', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            ['key' => 'first'],
            ['key' => 'second'],
        ],
    ]);

    $assert->has('bar', 1, function (AssertableJson $item) {
        $item->where('key', 'first');
    });
})->throws(AssertionFailedError::class, 'Property [bar] does not have the expected size.');

test('scope shorthand fails when asserting empty array', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [],
    ]);

    $assert->has('bar', 0, function (AssertableJson $item) {
        $item->where('key', 'first');
    });
})->throws(AssertionFailedError::class, 'Cannot scope directly onto the first element of property [bar] because it is empty.');

test('scope shorthand fails when asserting empty array without count', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [],
    ]);

    $assert->has('bar', null, function (AssertableJson $item) {
        $item->where('key', 'first');
    });
})->throws(AssertionFailedError::class, 'Cannot scope directly onto the first element of property [bar] because it is empty.');

test('scope shorthand fails when second argument unsupported type', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            ['key' => 'first'],
            ['key' => 'second'],
        ],
    ]);

    $assert->has('bar', 'invalid', function (AssertableJson $item) {
        $item->where('key', 'first');
    });
})->throws(TypeError::class);

test('first scope', function () {
    $assert = AssertableJson::fromArray([
        'foo' => [
            'key' => 'first',
        ],
        'bar' => [
            'key' => 'second',
        ],
    ]);

    $assert->first(function (AssertableJson $item) {
        $item->where('key', 'first');
    });
});

test('first scope fails when no props', function () {
    $assert = AssertableJson::fromArray([]);

    $assert->first(function (AssertableJson $item) {
        //
    });
})->throws(AssertionFailedError::class, 'Cannot scope directly onto the first element of the root level because it is empty.');

test('first nested scope fails when no props', function () {
    $assert = AssertableJson::fromArray([
        'foo' => [],
    ]);

    $assert->has('foo', function (AssertableJson $assert) {
        $assert->first(function (AssertableJson $item) {
            //
        });
    });
})->throws(AssertionFailedError::class, 'Cannot scope directly onto the first element of property [foo] because it is empty.');

test('first scope fails when prop single value', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 'bar',
    ]);

    $assert->first(function (AssertableJson $item) {
        //
    });
})->throws(AssertionFailedError::class, 'Property [foo] is not scopeable.');

test('each scope', function () {
    $assert = AssertableJson::fromArray([
        'foo' => [
            'key' => 'first',
        ],
        'bar' => [
            'key' => 'second',
        ],
    ]);

    $assert->each(function (AssertableJson $item) {
        $item->whereType('key', 'string');
    });
});

test('each scope fails when no props', function () {
    $assert = AssertableJson::fromArray([]);

    $assert->each(function (AssertableJson $item) {
        //
    });
})->throws(AssertionFailedError::class, 'Cannot scope directly onto each element of the root level because it is empty.');

test('each nested scope fails when no props', function () {
    $assert = AssertableJson::fromArray([
        'foo' => [],
    ]);

    $assert->has('foo', function (AssertableJson $assert) {
        $assert->each(function (AssertableJson $item) {
            //
        });
    });
})->throws(AssertionFailedError::class, 'Cannot scope directly onto each element of property [foo] because it is empty.');

test('each scope fails when prop single value', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 'bar',
    ]);

    $assert->each(function (AssertableJson $item) {
        //
    });
})->throws(AssertionFailedError::class, 'Property [foo] is not scopeable.');

test('fails when not interacting with all props in scope', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $assert->has('bar', function (AssertableJson $item) {
        $item->where('baz', 'example');
    });
})->throws(AssertionFailedError::class, 'Unexpected properties were found in scope [bar].');

test('disable interaction check for current scope', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $assert->has('bar', function (AssertableJson $item) {
        $item->etc();
    });
});

test('cannot disable interaction check for different scopes', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => [
                'foo' => 'bar',
                'example' => 'value',
            ],
            'prop' => 'value',
        ],
    ]);

    $assert->has('bar', function (AssertableJson $item) {
        $item
            ->etc()
            ->has('baz', function (AssertableJson $item) {
                //
            });
    });
})->throws(AssertionFailedError::class, 'Unexpected properties were found in scope [bar.baz].');

test('top level prop interaction disabled by default', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 'bar',
        'bar' => 'baz',
    ]);

    $assert->has('foo');
});

test('top level interaction enabled when interacted flag set', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 'bar',
        'bar' => 'baz',
    ]);

    $assert
        ->has('foo')
        ->interacted();
})->throws(AssertionFailedError::class, 'Unexpected properties were found on the root level.');

test('assert where all matches values', function () {
    $assert = AssertableJson::fromArray([
        'foo' => [
            'bar' => 'value',
            'example' => ['hello' => 'world'],
        ],
        'baz' => 'another',
    ]);

    $assert->whereAll([
        'foo.bar' => 'value',
        'foo.example' => ArrayableStubObject::make(['hello' => 'world']),
        'baz' => function ($value) {
            return $value === 'another';
        },
    ]);
});

test('assert where all fails when at least one prop does not match value', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 'bar',
        'baz' => 'example',
    ]);

    $assert->whereAll([
        'foo' => 'bar',
        'baz' => function ($value) {
            return $value === 'foo';
        },
    ]);
})->throws(AssertionFailedError::class, 'Property [baz] was marked as invalid using a closure.');

test('assert where type string', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 'bar',
    ]);

    $assert->whereType('foo', 'string');
});

test('assert where type integer', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 123,
    ]);

    $assert->whereType('foo', 'integer');
});

test('assert where type boolean', function () {
    $assert = AssertableJson::fromArray([
        'foo' => true,
    ]);

    $assert->whereType('foo', 'boolean');
});

test('assert where type double', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 12.3,
    ]);

    $assert->whereType('foo', 'double');
});

test('assert where type array', function () {
    $assert = AssertableJson::fromArray([
        'foo' => ['bar', 'baz'],
        'bar' => ['foo' => 'baz'],
    ]);

    $assert->whereType('foo', 'array');
    $assert->whereType('bar', 'array');
});

test('assert where type null', function () {
    $assert = AssertableJson::fromArray([
        'foo' => null,
    ]);

    $assert->whereType('foo', 'null');
});

test('assert where all type', function () {
    $assert = AssertableJson::fromArray([
        'one' => 'foo',
        'two' => 123,
        'three' => true,
        'four' => 12.3,
        'five' => ['foo', 'bar'],
        'six' => ['foo' => 'bar'],
        'seven' => null,
    ]);

    $assert->whereAllType([
        'one' => 'string',
        'two' => 'integer',
        'three' => 'boolean',
        'four' => 'double',
        'five' => 'array',
        'six' => 'array',
        'seven' => 'null',
    ]);
});

test('assert where type when wrong type is given', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 'bar',
    ]);

    $assert->whereType('foo', 'integer');
})->throws(AssertionFailedError::class, 'Property [foo] is not of expected type [integer].');

test('assert where type with union types', function () {
    $firstAssert = AssertableJson::fromArray([
        'foo' => 'bar',
    ]);

    $secondAssert = AssertableJson::fromArray([
        'foo' => null,
    ]);

    $firstAssert->whereType('foo', ['string', 'null']);
    $secondAssert->whereType('foo', ['string', 'null']);
});

test('assert where type when wrong union type is given', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 123,
    ]);

    $assert->whereType('foo', ['string', 'null']);
})->throws(AssertionFailedError::class, 'Property [foo] is not of expected type [string|null].');

test('assert where type with pipe in union type', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 'bar',
    ]);

    $assert->whereType('foo', 'string|null');
});

test('assert where type with pipe in wrong union type', function () {
    $assert = AssertableJson::fromArray([
        'foo' => 'bar',
    ]);

    $assert->whereType('foo', 'integer|null');
})->throws(AssertionFailedError::class, 'Property [foo] is not of expected type [integer|null].');

test('assert has all', function () {
    $assert = AssertableJson::fromArray([
        'foo' => [
            'bar' => 'value',
            'example' => ['hello' => 'world'],
        ],
        'baz' => 'another',
    ]);

    $assert->hasAll([
        'foo.bar',
        'foo.example',
        'baz',
    ]);
});

test('assert has all fails when at least one prop missing', function () {
    $assert = AssertableJson::fromArray([
        'foo' => [
            'bar' => 'value',
            'example' => ['hello' => 'world'],
        ],
        'baz' => 'another',
    ]);

    $assert->hasAll([
        'foo.bar',
        'foo.baz',
        'baz',
    ]);
})->throws(AssertionFailedError::class, 'Property [foo.baz] does not exist.');

test('assert has all accepts multiple arguments instead of array', function () {
    $assert = AssertableJson::fromArray([
        'foo' => [
            'bar' => 'value',
            'example' => ['hello' => 'world'],
        ],
        'baz' => 'another',
    ]);

    $assert->hasAll('foo.bar', 'foo.example', 'baz');

    $assert->hasAll('foo.bar', 'foo.baz', 'baz');
})->throws(AssertionFailedError::class, 'Property [foo.baz] does not exist.');

test('assert count multiple props', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'key' => 'value',
            'prop' => 'example',
        ],
        'baz' => [
            'another' => 'value',
        ],
    ]);

    $assert->hasAll([
        'bar' => 2,
        'baz' => 1,
    ]);
});

test('assert count multiple props fails when prop missing', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'key' => 'value',
            'prop' => 'example',
        ],
    ]);

    $assert->hasAll([
        'bar' => 2,
        'baz' => 1,
    ]);
})->throws(AssertionFailedError::class, 'Property [baz] does not exist.');

test('macroable', function () {
    AssertableJson::macro('myCustomMacro', function () {
        throw new RuntimeException('My Custom Macro was called!');
    });

    $assert = AssertableJson::fromArray(['foo' => 'bar']);
    $assert->myCustomMacro();
})->throws(RuntimeException::class, 'My Custom Macro was called!');

test('tappable', function () {
    $assert = AssertableJson::fromArray([
        'bar' => [
            'baz' => 'example',
            'prop' => 'value',
        ],
    ]);

    $called = false;
    $assert->has('bar', function (AssertableJson $assert) use (&$called) {
        $assert->etc();
        $assert->tap(function (AssertableJson $assert) use (&$called) {
            $called = true;
        });
    });

    expect($called)->toBeTrue();
});
