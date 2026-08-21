<?php

use Tests\NutsAndBolts\Fixtures\FluentArrayIteratorStub;
use Tests\NutsAndBolts\TestBackedEnum;
use Tests\NutsAndBolts\TestEnum;
use Tests\NutsAndBolts\TestStringBackedEnum;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\Stringable;
use Voyager\NutsAndBolts\Fluent;

/** Read the private `attributes` property straight off a Fluent. */
function fluentAttributes(Fluent $fluent): array
{
    return (new ReflectionObject($fluent))->getProperty('attributes')->getValue($fluent);
}

describe('construction', function () {
    test('an array populates the attributes', function () {
        $array = ['name' => 'Taylor', 'age' => 25];
        $fluent = new Fluent($array);

        expect(fluentAttributes($fluent))->toEqual($array)
            ->and($fluent->getAttributes())->toEqual($array);
    });

    test('an stdClass populates the attributes', function () {
        $array = ['name' => 'Taylor', 'age' => 25];
        $fluent = new Fluent((object) $array);

        expect(fluentAttributes($fluent))->toEqual($array)
            ->and($fluent->getAttributes())->toEqual($array);
    });

    test('an IteratorAggregate populates the attributes', function () {
        $array = ['name' => 'Taylor', 'age' => 25];
        $fluent = new Fluent(new FluentArrayIteratorStub($array));

        expect(fluentAttributes($fluent))->toEqual($array)
            ->and($fluent->getAttributes())->toEqual($array);
    });

    test('fill merges new attributes in', function () {
        $fluent = new Fluent(['name' => 'John Doe']);

        $fluent->fill([
            'email' => 'john.doe@example.com',
            'age' => 30,
        ]);

        expect($fluent->getAttributes())->toEqual([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'age' => 30,
        ]);
    });
});

describe('reading and writing', function () {
    test('get returns the attribute or the default', function () {
        $fluent = new Fluent(['name' => 'Taylor']);

        expect($fluent->get('name'))->toBe('Taylor')
            ->and($fluent->get('foo', 'Default'))->toBe('Default')
            ->and($fluent->name)->toBe('Taylor')
            ->and($fluent->foo)->toBeNull();
    });

    test('set writes an attribute, dot notation included', function () {
        $fluent = new Fluent;

        $fluent->set('name', 'Taylor');
        $fluent->set('developer', true);
        $fluent->set('posts', 25);
        $fluent->set('computer.color', 'silver');

        expect($fluent->name)->toBe('Taylor')
            ->and($fluent->developer)->toBeTrue()
            ->and($fluent->posts)->toBe(25)
            ->and($fluent->computer)->toBe(['color' => 'silver']);
    });

    test('attributes are reachable by array access', function () {
        $fluent = new Fluent(['attributes' => '1']);

        expect(isset($fluent['attributes']))->toBeTrue()
            ->and($fluent['attributes'])->toEqual(1);

        $fluent->attributes();

        expect($fluent['attributes'])->toBeTrue();
    });

    test('magic methods set attributes and return the Fluent', function () {
        $fluent = new Fluent;

        $fluent->name = 'Taylor';
        $fluent->developer();
        $fluent->age(25);

        expect($fluent->name)->toBe('Taylor')
            ->and($fluent->developer)->toBeTrue()
            ->and($fluent->age)->toEqual(25)
            ->and($fluent->programmer())->toBeInstanceOf(Fluent::class);
    });

    test('isset and unset work on attributes', function () {
        $fluent = new Fluent(['name' => 'Taylor', 'age' => 25]);

        expect(isset($fluent->name))->toBeTrue();

        unset($fluent->name);

        expect(isset($fluent->name))->toBeFalse();
    });

    test('a Fluent is iterable', function () {
        $fluent = new Fluent([
            'name' => 'Taylor',
            'role' => 'admin',
        ]);

        $result = [];

        foreach ($fluent as $key => $value) {
            $result[$key] = $value;
        }

        expect($result)->toBe([
            'name' => 'Taylor',
            'role' => 'admin',
        ]);
    });

    test('a fresh Fluent is empty', function () {
        $fluent = new Fluent;

        expect($fluent->isEmpty())->toBeTrue()
            ->and($fluent->isNotEmpty())->toBeFalse();
    });

    test('a populated Fluent is not empty', function () {
        $fluent = new Fluent([
            'name' => 'Taylor',
            'role' => 'admin',
        ]);

        expect($fluent->isNotEmpty())->toBeTrue()
            ->and($fluent->isEmpty())->toBeFalse();
    });
});

describe('serialization', function () {
    test('toArray returns the attributes', function () {
        $array = ['name' => 'Taylor', 'age' => 25];

        expect((new Fluent($array))->toArray())->toEqual($array);
    });

    test('toJson encodes the toArray result', function () {
        $fluent = Mockery::mock(Fluent::class)->makePartial();
        $fluent->shouldReceive('toArray')->once()->andReturn(['foo']);

        expect($fluent->toJson())->toBe(json_encode(['foo']));
    });

    test('toPrettyJson pretty-prints the toArray result', function () {
        $fluent = Mockery::mock(Fluent::class)->makePartial();
        $fluent->shouldReceive('toArray')->twice()->andReturn(['foo' => 'bar', 'bar' => 'foo']);

        $results = $fluent->toPrettyJson();
        $expected = $fluent->toJson(JSON_PRETTY_PRINT);

        expect($results)->toBe($expected)
            ->and($results)->toContain("\n")
            ->and($results)->toContain('    ');
    });
});

describe('scoping', function () {
    test('scope narrows to a dotted key, with a default', function () {
        $fluent = new Fluent(['user' => ['name' => 'taylor']]);

        expect($fluent->scope('user.name')->toArray())->toEqual(['taylor'])
            ->and($fluent->scope('user.age', 'dayle')->toArray())->toEqual(['dayle']);

        $fluent = new Fluent(['products' => ['forge', 'vapour', 'spark']]);

        expect($fluent->scope('products')->toArray())->toEqual(['forge', 'vapour', 'spark'])
            ->and($fluent->scope('missing', ['foo', 'bar'])->toArray())->toEqual(['foo', 'bar']);

        $fluent = new Fluent(['authors' => ['taylor' => ['products' => ['forge', 'vapour', 'spark']]]]);

        expect($fluent->scope('authors.taylor.products')->toArray())->toEqual(['forge', 'vapour', 'spark']);
    });

    test('collect returns a Collection of the whole bag or a dotted key', function () {
        expect((new Fluent(['forge', 'vapour', 'spark']))->collect()->all())->toEqual(['forge', 'vapour', 'spark'])
            ->and((new Fluent(['authors' => ['taylor' => ['products' => ['forge', 'vapour', 'spark']]]]))->collect('authors.taylor.products')->all())
            ->toEqual(['forge', 'vapour', 'spark']);
    });
});

describe('typed accessors', function () {
    test('string returns a Stringable, casting as it goes', function () {
        $fluent = new Fluent([
            'int' => 123,
            'int_str' => '456',
            'float' => 123.456,
            'float_str' => '123.456',
            'float_zero' => 0.000,
            'float_str_zero' => '0.000',
            'str' => 'abc',
            'empty_str' => '',
            'null' => null,
        ]);

        expect($fluent->string('int'))->toBeInstanceOf(Stringable::class)
            ->and($fluent->string('unknown_key'))->toBeInstanceOf(Stringable::class)
            ->and($fluent->string('int')->value())->toBe('123')
            ->and($fluent->string('int_str')->value())->toBe('456')
            ->and($fluent->string('float')->value())->toBe('123.456')
            ->and($fluent->string('float_str')->value())->toBe('123.456')
            ->and($fluent->string('float_zero')->value())->toBe('0')
            ->and($fluent->string('float_str_zero')->value())->toBe('0.000')
            ->and($fluent->string('empty_str')->value())->toBe('')
            ->and($fluent->string('null')->value())->toBe('')
            ->and($fluent->string('unknown_key')->value())->toBe('');
    });

    test('boolean reads the usual truthy strings', function () {
        $fluent = new Fluent(['with_trashed' => 'false', 'download' => true, 'checked' => 1, 'unchecked' => '0', 'with_on' => 'on', 'with_yes' => 'yes']);

        expect($fluent->boolean('checked'))->toBeTrue()
            ->and($fluent->boolean('download'))->toBeTrue()
            ->and($fluent->boolean('unchecked'))->toBeFalse()
            ->and($fluent->boolean('with_trashed'))->toBeFalse()
            ->and($fluent->boolean('some_undefined_key'))->toBeFalse()
            ->and($fluent->boolean('with_on'))->toBeTrue()
            ->and($fluent->boolean('with_yes'))->toBeTrue();
    });

    test('integer casts to int and honours the default', function () {
        $fluent = new Fluent([
            'int' => '123',
            'raw_int' => 456,
            'zero_padded' => '078',
            'space_padded' => ' 901',
            'nan' => 'nan',
            'mixed' => '1ab',
            'underscore_notation' => '2_000',
            'null' => null,
        ]);

        expect($fluent->integer('int'))->toBe(123)
            ->and($fluent->integer('raw_int'))->toBe(456)
            ->and($fluent->integer('zero_padded'))->toBe(78)
            ->and($fluent->integer('space_padded'))->toBe(901)
            ->and($fluent->integer('nan'))->toBe(0)
            ->and($fluent->integer('mixed'))->toBe(1)
            ->and($fluent->integer('underscore_notation'))->toBe(2)
            ->and($fluent->integer('unknown_key', 123456))->toBe(123456)
            ->and($fluent->integer('null'))->toBe(0)
            ->and($fluent->integer('null', 123456))->toBe(0);
    });

    test('float casts to float and honours the default', function () {
        $fluent = new Fluent([
            'float' => '1.23',
            'raw_float' => 45.6,
            'decimal_only' => '.6',
            'zero_padded' => '0.78',
            'space_padded' => ' 90.1',
            'nan' => 'nan',
            'mixed' => '1.ab',
            'scientific_notation' => '1e3',
            'null' => null,
        ]);

        expect($fluent->float('float'))->toBe(1.23)
            ->and($fluent->float('raw_float'))->toBe(45.6)
            ->and($fluent->float('decimal_only'))->toBe(.6)
            ->and($fluent->float('zero_padded'))->toBe(0.78)
            ->and($fluent->float('space_padded'))->toBe(90.1)
            ->and($fluent->float('nan'))->toBe(0.0)
            ->and($fluent->float('mixed'))->toBe(1.0)
            ->and($fluent->float('scientific_notation'))->toBe(1e3)
            ->and($fluent->float('unknown_key', 123.456))->toBe(123.456)
            ->and($fluent->float('null'))->toBe(0.0)
            ->and($fluent->float('null', 123.456))->toBe(0.0);
    });

    test('array returns the whole bag, one key, or a subset', function () {
        $fluent = new Fluent(['users' => [1, 2, 3]]);

        expect($fluent->array('users'))->toBeArray()
            ->and($fluent->array('users'))->toEqual([1, 2, 3])
            ->and($fluent->array())->toEqual(['users' => [1, 2, 3]]);

        expect((new Fluent(['text-payload']))->array())->toEqual(['text-payload']);
        expect((new Fluent(['email' => 'test@example.com']))->array('email'))->toEqual(['test@example.com']);

        $fluent = new Fluent([]);

        expect($fluent->array())->toBeArray()->toBeEmpty();

        $fluent = new Fluent(['users' => [1, 2, 3], 'roles' => [4, 5, 6], 'foo' => ['bar', 'baz'], 'email' => 'test@example.com']);

        expect($fluent->array(['developers']))->toBeEmpty()
            ->and($fluent->array(['roles']))->not->toBeEmpty()
            ->and($fluent->array(['roles']))->toEqual(['roles' => [4, 5, 6]])
            ->and($fluent->array(['users', 'email']))->toEqual(['users' => [1, 2, 3], 'email' => 'test@example.com'])
            ->and($fluent->array(['roles', 'foo']))->toEqual(['roles' => [4, 5, 6], 'foo' => ['bar', 'baz']])
            ->and($fluent->array())->toEqual(['users' => [1, 2, 3], 'roles' => [4, 5, 6], 'foo' => ['bar', 'baz'], 'email' => 'test@example.com']);
    });

    test('collect mirrors array but returns Collections', function () {
        $fluent = new Fluent(['users' => [1, 2, 3]]);

        expect($fluent->collect('users'))->toBeInstanceOf(Collection::class)
            ->and($fluent->collect('developers')->isEmpty())->toBeTrue()
            ->and($fluent->collect('users')->all())->toEqual([1, 2, 3])
            ->and($fluent->collect()->all())->toEqual(['users' => [1, 2, 3]]);

        expect((new Fluent(['text-payload']))->collect()->all())->toEqual(['text-payload']);
        expect((new Fluent(['email' => 'test@example.com']))->collect('email')->all())->toEqual(['test@example.com']);

        $fluent = new Fluent([]);

        expect($fluent->collect())->toBeInstanceOf(Collection::class)
            ->and($fluent->collect()->isEmpty())->toBeTrue();

        $fluent = new Fluent(['users' => [1, 2, 3], 'roles' => [4, 5, 6], 'foo' => ['bar', 'baz'], 'email' => 'test@example.com']);

        expect($fluent->collect(['users']))->toBeInstanceOf(Collection::class)
            ->and($fluent->collect(['developers'])->isEmpty())->toBeTrue()
            ->and($fluent->collect(['roles'])->isNotEmpty())->toBeTrue()
            ->and($fluent->collect(['roles'])->all())->toEqual(['roles' => [4, 5, 6]])
            ->and($fluent->collect(['users', 'email'])->all())->toEqual(['users' => [1, 2, 3], 'email' => 'test@example.com'])
            ->and($fluent->collect(['roles', 'foo']))->toEqual(collect(['roles' => [4, 5, 6], 'foo' => ['bar', 'baz']]))
            ->and($fluent->collect()->all())->toEqual(['users' => [1, 2, 3], 'roles' => [4, 5, 6], 'foo' => ['bar', 'baz'], 'email' => 'test@example.com']);
    });
});

describe('date', function () {
    test('date parses the value, with an optional format and timezone', function () {
        $fluent = new Fluent([
            'as_null' => null,
            'as_invalid' => 'invalid',

            'as_datetime' => '20-01-01 16:30:25',
            'as_format' => '1577896225',
            'as_timezone' => '20-01-01 13:30:25',

            'as_date' => '2020-01-01',
            'as_time' => '16:30:25',
        ]);

        $current = Carbon::create(2020, 1, 1, 16, 30, 25);

        expect($fluent->date('as_null'))->toBeNull()
            ->and($fluent->date('doesnt_exists'))->toBeNull()
            ->and($fluent->date('as_datetime'))->toEqual($current)
            ->and($fluent->date('as_format', 'U')->format('Y-m-d H:i:s P'))->toEqual($current->format('Y-m-d H:i:s P'))
            ->and($fluent->date('as_timezone', null, 'America/Santiago'))->toEqual($current)
            ->and($fluent->date('as_date')->isSameDay($current))->toBeTrue()
            ->and($fluent->date('as_time')->isSameSecond('16:30:25'))->toBeTrue();
    });

    test('an unparseable value throws', function () {
        (new Fluent(['date' => 'invalid']))->date('date');
    })->throws(InvalidArgumentException::class);

    test('an invalid format throws', function () {
        (new Fluent(['date' => '20-01-01 16:30:25']))->date('date', 'invalid_format');
    })->throws(InvalidArgumentException::class);
});

describe('enums', function () {
    test('enum resolves a backed enum, or null', function () {
        $fluent = new Fluent([
            'valid_enum_value' => 'A',
            'invalid_enum_value' => 'invalid',
            'empty_value_request' => '',
            'string' => [
                'a' => '1',
                'b' => '2',
                'doesnt_exist' => '-1024',
            ],
            'int' => [
                'a' => 1,
                'b' => 2,
                'doesnt_exist' => 1024,
            ],
        ]);

        expect($fluent->enum('doesnt_exist', TestEnum::class))->toBeNull()
            ->and($fluent->enum('valid_enum_value', TestStringBackedEnum::class))->toEqual(TestStringBackedEnum::A)
            ->and($fluent->enum('invalid_enum_value', TestStringBackedEnum::class))->toBeNull()
            ->and($fluent->enum('empty_value_request', TestStringBackedEnum::class))->toBeNull()
            ->and($fluent->enum('valid_enum_value', TestEnum::class))->toBeNull()
            ->and($fluent->enum('string.a', TestBackedEnum::class))->toEqual(TestBackedEnum::A)
            ->and($fluent->enum('string.b', TestBackedEnum::class))->toEqual(TestBackedEnum::B)
            ->and($fluent->enum('string.doesnt_exist', TestBackedEnum::class))->toBeNull()
            ->and($fluent->enum('int.a', TestBackedEnum::class))->toEqual(TestBackedEnum::A)
            ->and($fluent->enum('int.b', TestBackedEnum::class))->toEqual(TestBackedEnum::B)
            ->and($fluent->enum('int.doesnt_exist', TestBackedEnum::class))->toBeNull();
    });

    test('enums resolves a list of backed enums, dropping the invalid ones', function () {
        $fluent = new Fluent([
            'valid_enum_values' => ['A', 'B'],
            'invalid_enum_values' => ['invalid', 'invalid'],
            'empty_value_request' => [],
            'string' => [
                'a' => ['1', '2'],
                'b' => '2',
                'doesnt_exist' => '-1024',
            ],
            'int' => [
                'a' => [1, 2],
                'b' => 2,
                'doesnt_exist' => 1024,
            ],
        ]);

        expect($fluent->enums('doesnt_exist', TestEnum::class))->toBeEmpty()
            ->and($fluent->enums('valid_enum_values', TestStringBackedEnum::class))->toEqual([TestStringBackedEnum::A, TestStringBackedEnum::B])
            ->and($fluent->enums('invalid_enum_value', TestStringBackedEnum::class))->toBeEmpty()
            ->and($fluent->enums('empty_value_request', TestStringBackedEnum::class))->toBeEmpty()
            ->and($fluent->enums('valid_enum_value', TestEnum::class))->toBeEmpty()
            ->and($fluent->enums('string.a', TestBackedEnum::class))->toEqual([TestBackedEnum::A, TestBackedEnum::B])
            ->and($fluent->enums('string.b', TestBackedEnum::class))->toEqual([TestBackedEnum::B])
            ->and($fluent->enums('string.doesnt_exist', TestBackedEnum::class))->toBeEmpty()
            ->and($fluent->enums('int.a', TestBackedEnum::class))->toEqual([TestBackedEnum::A, TestBackedEnum::B])
            ->and($fluent->enums('int.b', TestBackedEnum::class))->toEqual([TestBackedEnum::B])
            ->and($fluent->enums('int.doesnt_exist', TestBackedEnum::class))->toBeEmpty();
    });
});

test('Fluent is macroable', function () {
    Fluent::macro('foo', function () {
        return $this->fill([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);
    });

    $fluent = new Fluent([
        'bee' => 'ser',
    ]);

    expect($fluent->foo()->all())->toBe([
        'bee' => 'ser',
        'foo' => 'bar',
        'baz' => 'zal',
    ]);
});
