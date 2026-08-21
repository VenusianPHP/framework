<?php

use Voyager\Config\Repository;
use Voyager\NutsAndBolts\Collection;

beforeEach(function () {
    $this->config = [
        'foo' => 'bar',
        'bar' => 'baz',
        'baz' => 'bat',
        'null' => null,
        'boolean' => true,
        'integer' => 1,
        'float' => 1.1,
        'associate' => [
            'x' => 'xxx',
            'y' => 'yyy',
        ],
        'array' => [
            'aaa',
            'zzz',
        ],
        'x' => [
            'z' => 'zoo',
        ],
        'a.b' => 'c',
        'a' => [
            'b.c' => 'd',
        ],
    ];

    $this->repository = new Repository($this->config);
});

test('a literal dotted key wins over the nested path it looks like', function () {
    expect($this->repository->get('a.b'))->toBe('c')
        ->and($this->repository->get('a.b.c'))->toBeNull()
        ->and($this->repository->get('x.y.z'))->toBeNull()
        ->and($this->repository->get('.'))->toBeNull();
});

test('it gets a boolean value', function () {
    expect($this->repository->get('boolean'))->toBeTrue();
});

test('it gets a null value', function () {
    expect($this->repository->get('null'))->toBeNull();
});

test('it constructs from an array of items', function () {
    expect($this->repository)->toBeInstanceOf(Repository::class);
});

test('has is true for a known key', function () {
    expect($this->repository->has('foo'))->toBeTrue();
});

test('has is false for an unknown key', function () {
    expect($this->repository->has('not-exist'))->toBeFalse();
});

test('it gets a single value', function () {
    expect($this->repository->get('foo'))->toBe('bar');
});

test('get accepts an array of keys, with defaults keyed by name', function () {
    expect($this->repository->get([
        'foo',
        'bar',
        'none',
    ]))->toBe([
        'foo' => 'bar',
        'bar' => 'baz',
        'none' => null,
    ]);

    expect($this->repository->get([
        'x.y' => 'default',
        'x.z' => 'default',
        'bar' => 'default',
        'baz',
    ]))->toBe([
        'x.y' => 'default',
        'x.z' => 'zoo',
        'bar' => 'baz',
        'baz' => 'bat',
    ]);
});

test('getMany accepts an array of keys, with defaults keyed by name', function () {
    expect($this->repository->getMany([
        'foo',
        'bar',
        'none',
    ]))->toBe([
        'foo' => 'bar',
        'bar' => 'baz',
        'none' => null,
    ]);

    expect($this->repository->getMany([
        'x.y' => 'default',
        'x.z' => 'default',
        'bar' => 'default',
        'baz',
    ]))->toBe([
        'x.y' => 'default',
        'x.z' => 'zoo',
        'bar' => 'baz',
        'baz' => 'bat',
    ]);
});

test('get falls back to the given default', function () {
    expect($this->repository->get('not-exist', 'default'))->toBe('default');
});

test('it sets a single value', function () {
    $this->repository->set('key', 'value');

    expect($this->repository->get('key'))->toBe('value');
});

test('it sets an array of values', function () {
    $this->repository->set([
        'key1' => 'value1',
        'key2' => 'value2',
        'key3',
        'key4' => [
            'foo' => 'bar',
            'bar' => [
                'foo' => 'bar',
            ],
        ],
    ]);

    expect($this->repository->get('key1'))->toBe('value1')
        ->and($this->repository->get('key2'))->toBe('value2')
        ->and($this->repository->get('key3'))->toBeNull()
        ->and($this->repository->get('key4.foo'))->toBe('bar')
        ->and($this->repository->get('key4.bar.foo'))->toBe('bar')
        ->and($this->repository->get('key5'))->toBeNull();
});

test('prepend puts a value at the front of an array', function () {
    expect($this->repository->get('array.0'))->toBe('aaa')
        ->and($this->repository->get('array.1'))->toBe('zzz');

    $this->repository->prepend('array', 'xxx');

    expect($this->repository->get('array.0'))->toBe('xxx')
        ->and($this->repository->get('array.1'))->toBe('aaa')
        ->and($this->repository->get('array.2'))->toBe('zzz')
        ->and($this->repository->get('array.3'))->toBeNull()
        ->and($this->repository->get('array'))->toHaveCount(3);
});

test('push puts a value at the end of an array', function () {
    expect($this->repository->get('array.0'))->toBe('aaa')
        ->and($this->repository->get('array.1'))->toBe('zzz');

    $this->repository->push('array', 'xxx');

    expect($this->repository->get('array.0'))->toBe('aaa')
        ->and($this->repository->get('array.1'))->toBe('zzz')
        ->and($this->repository->get('array.2'))->toBe('xxx')
        ->and($this->repository->get('array'))->toHaveCount(3);
});

test('prepend creates the array when the key is new', function () {
    $this->repository->prepend('new_key', 'xxx');

    expect($this->repository->get('new_key'))->toBe(['xxx']);
});

test('push creates the array when the key is new', function () {
    $this->repository->push('new_key', 'xxx');

    expect($this->repository->get('new_key'))->toBe(['xxx']);
});

test('all returns every item', function () {
    expect($this->repository->all())->toBe($this->config);
});

test('offset exists', function () {
    $this->repository->set([
        'foo' => 'bar',
        'null_value' => null,
        'empty_string' => '',
        'numeric_value' => 123,
    ]);

    expect(isset($this->repository['foo']))->toBeTrue()
        ->and(isset($this->repository['not-exist']))->toBeFalse()
        ->and(isset($this->repository['null_value']))->toBeTrue()
        ->and(isset($this->repository['empty_string']))->toBeTrue()
        ->and(isset($this->repository['numeric_value']))->toBeTrue()
        ->and(isset($this->repository[-1]))->toBeFalse()
        ->and(isset($this->repository['non_numeric']))->toBeFalse();
});

test('offset get', function () {
    expect($this->repository['not-exist'])->toBeNull()
        ->and($this->repository['foo'])->toBe('bar')
        ->and($this->repository['associate'])->toBe([
            'x' => 'xxx',
            'y' => 'yyy',
        ]);
});

test('offset set', function () {
    expect($this->repository['key'])->toBeNull();

    $this->repository['key'] = 'value';

    expect($this->repository['key'])->toBe('value');

    $this->repository['key'] = 'new_value';

    expect($this->repository['key'])->toBe('new_value');

    $this->repository['new_key'] = null;

    expect($this->repository['new_key'])->toBeNull();

    $this->repository[''] = 'value';

    expect($this->repository[''])->toBe('value');

    $this->repository[123] = '123';

    expect($this->repository[123])->toBe('123');
});

test('offset unset nulls the value but keeps the key', function () {
    expect($this->repository->all())->toHaveKey('associate')
        ->and($this->repository->get('associate'))->toBe($this->config['associate']);

    unset($this->repository['associate']);

    expect($this->repository->all())->toHaveKey('associate')
        ->and($this->repository->get('associate'))->toBeNull();
});

test('it is macroable', function () {
    $this->repository->macro('foo', fn () => 'macroable');

    expect($this->repository->foo())->toBe('macroable');
});

test('it gets as string', function () {
    expect($this->repository->string('a.b'))->toBe('c');
});

test('it throws an exception when trying to get non string value as string', function () {
    $this->repository->string('a');
})->throws(InvalidArgumentException::class, 'Configuration value for key [a] must be a string,');

test('it gets as array', function () {
    expect($this->repository->array('array'))->toBe(['aaa', 'zzz']);
});

test('it throws an exception when trying to get non array value as array', function () {
    $this->repository->array('a.b');
})->throws(InvalidArgumentException::class, 'Configuration value for key [a.b] must be an array,');

test('it gets as collection', function () {
    $collection = $this->repository->collection('array');

    expect($collection)->toBeInstanceOf(Collection::class)
        ->and($collection->toArray())->toBe(['aaa', 'zzz']);
});

test('it gets as boolean', function () {
    expect($this->repository->boolean('boolean'))->toBeTrue();
});

test('it throws an exception when trying to get non boolean value as boolean', function () {
    $this->repository->boolean('a.b');
})->throws(InvalidArgumentException::class, 'Configuration value for key [a.b] must be a boolean,');

test('it gets as integer', function () {
    expect($this->repository->integer('integer'))->toBe(1);
});

test('it throws an exception when trying to get non integer value as integer', function () {
    $this->repository->integer('a.b');
})->throws(InvalidArgumentException::class, 'Configuration value for key [a.b] must be an integer,');

test('it gets as float', function () {
    expect($this->repository->float('float'))->toBe(1.1);
});

test('it throws an exception when trying to get non float value as float', function () {
    $this->repository->float('a.b');
})->throws(InvalidArgumentException::class, 'Configuration value for key [a.b] must be a float,');
