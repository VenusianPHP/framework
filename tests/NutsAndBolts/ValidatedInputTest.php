<?php

use Tests\NutsAndBolts\Fixtures\StringBackedEnum;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\Stringable;
use Voyager\NutsAndBolts\ValidatedInput;

/** The bag most of the presence-check cases are written against. */
function presenceInput(): ValidatedInput
{
    return new ValidatedInput(['name' => 'Fatih', 'surname' => 'AYDIN', 'foo' => ['bar' => null, 'baz' => '']]);
}

/** The numeric/string bag the typed accessors are written against. */
function stringyInput(): ValidatedInput
{
    return new ValidatedInput([
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
}

describe('access', function () {
    test('input is readable as a property, an offset and in bulk', function () {
        $input = new ValidatedInput(['name' => 'Taylor', 'votes' => 100]);

        expect($input->name)->toBe('Taylor')
            ->and($input['name'])->toBe('Taylor')
            ->and($input->all(['name']))->toEqual(['name' => 'Taylor'])
            ->and($input->only(['name']))->toEqual(['name' => 'Taylor'])
            ->and($input->except(['votes']))->toEqual(['name' => 'Taylor'])
            ->and($input->all())->toEqual(['name' => 'Taylor', 'votes' => 100]);
    });

    test('merge returns a bag with the extra items', function () {
        $input = (new ValidatedInput(['name' => 'Taylor']))->merge(['votes' => 100]);

        expect($input->name)->toBe('Taylor')
            ->and($input['name'])->toBe('Taylor')
            ->and($input->only(['name']))->toEqual(['name' => 'Taylor'])
            ->and($input->except(['votes']))->toEqual(['name' => 'Taylor'])
            ->and($input->all())->toEqual(['name' => 'Taylor', 'votes' => 100]);
    });

    test('keys returns the top-level keys', function () {
        expect(presenceInput()->keys())->toEqual(['name', 'surname', 'foo']);
    });

    test('all returns everything', function () {
        expect(presenceInput()->all())->toEqual(['name' => 'Fatih', 'surname' => 'AYDIN', 'foo' => ['bar' => null, 'baz' => '']]);
    });

    test('input reads a dotted key with a default', function () {
        $input = presenceInput();

        expect($input->input('name'))->toBe('Fatih')
            ->and($input->input('foo.bar'))->toBeNull()
            ->and($input->input('foo.bat', 'test'))->toBe('test');
    });

    test('only narrows to the given keys, dotted keys included', function () {
        $input = presenceInput();

        expect($input->only('name', 'surname', 'foo.bar'))->toEqual(['name' => 'Fatih', 'surname' => 'AYDIN', 'foo' => ['bar' => null]])
            ->and($input->only('name', 'foo'))->toEqual(['name' => 'Fatih', 'foo' => ['bar' => null, 'baz' => '']])
            ->and($input->only('foo.baz'))->toEqual(['foo' => ['baz' => '']])
            ->and($input->only('name'))->toEqual(['name' => 'Fatih']);
    });

    test('except removes the given keys, dotted keys included', function () {
        $input = presenceInput();

        expect($input->except('foo.baz'))->toEqual(['name' => 'Fatih', 'surname' => 'AYDIN', 'foo' => ['bar' => null]])
            ->and($input->except('name', 'foo'))->toEqual(['surname' => 'AYDIN'])
            ->and($input->except('name', 'surname', 'foo'))->toEqual([]);
    });
});

describe('presence checks', function () {
    test('has and missing report on a single bag', function () {
        $inputA = new ValidatedInput(['name' => 'Taylor']);

        expect($inputA->has('name'))->toBeTrue()
            ->and($inputA->missing('votes'))->toBeTrue()
            ->and($inputA->missing(['votes']))->toBeTrue()
            ->and($inputA->missing('name'))->toBeFalse();

        $inputB = new ValidatedInput(['name' => 'Taylor', 'votes' => 100]);

        expect($inputB->has(['name', 'votes']))->toBeTrue();
    });

    test('exists requires every key', function () {
        $input = presenceInput();

        expect($input->exists('name'))->toBeTrue()
            ->and($input->exists('surname'))->toBeTrue()
            ->and($input->exists(['name', 'surname']))->toBeTrue()
            ->and($input->exists('foo.bar'))->toBeTrue()
            ->and($input->exists(['name', 'foo.baz']))->toBeTrue()
            ->and($input->exists(['name', 'foo']))->toBeTrue()
            ->and($input->exists('foo'))->toBeTrue()
            ->and($input->exists('votes'))->toBeFalse()
            ->and($input->exists(['name', 'votes']))->toBeFalse()
            ->and($input->exists(['votes', 'foo.bar']))->toBeFalse();
    });

    test('has requires every key', function () {
        $input = presenceInput();

        expect($input->has('name'))->toBeTrue()
            ->and($input->has('surname'))->toBeTrue()
            ->and($input->has(['name', 'surname']))->toBeTrue()
            ->and($input->has('foo.bar'))->toBeTrue()
            ->and($input->has(['name', 'foo.baz']))->toBeTrue()
            ->and($input->has(['name', 'foo']))->toBeTrue()
            ->and($input->has('foo'))->toBeTrue()
            ->and($input->has('votes'))->toBeFalse()
            ->and($input->has(['name', 'votes']))->toBeFalse()
            ->and($input->has(['votes', 'foo.bar']))->toBeFalse();
    });

    test('hasAny requires only one key', function () {
        $input = presenceInput();

        expect($input->hasAny('name'))->toBeTrue()
            ->and($input->hasAny('surname'))->toBeTrue()
            ->and($input->hasAny('foo.bar'))->toBeTrue()
            ->and($input->hasAny(['name', 'surname']))->toBeTrue()
            ->and($input->hasAny(['name', 'foo.bat']))->toBeTrue()
            ->and($input->hasAny(['votes', 'foo']))->toBeTrue()
            ->and($input->hasAny('votes'))->toBeFalse()
            ->and($input->hasAny(['votes', 'foo.bat']))->toBeFalse();
    });

    test('missing requires every key to be absent', function () {
        $input = presenceInput();

        expect($input->missing('name'))->toBeFalse()
            ->and($input->missing('surname'))->toBeFalse()
            ->and($input->missing(['name', 'surname']))->toBeFalse()
            ->and($input->missing('foo.bar'))->toBeFalse()
            ->and($input->missing(['name', 'foo.baz']))->toBeFalse()
            ->and($input->missing(['name', 'foo']))->toBeFalse()
            ->and($input->missing('foo'))->toBeFalse()
            ->and($input->missing('votes'))->toBeTrue()
            ->and($input->missing(['name', 'votes']))->toBeTrue()
            ->and($input->missing(['votes', 'foo.bar']))->toBeTrue();
    });

    test('filled requires every key to hold a non-empty value', function () {
        $input = presenceInput();

        expect($input->filled('name'))->toBeTrue()
            ->and($input->filled('surname'))->toBeTrue()
            ->and($input->filled(['name', 'surname']))->toBeTrue()
            ->and($input->filled(['name', 'foo']))->toBeTrue()
            ->and($input->filled('foo'))->toBeTrue()
            ->and($input->filled('foo.bar'))->toBeFalse()
            ->and($input->filled(['name', 'foo.baz']))->toBeFalse()
            ->and($input->filled('votes'))->toBeFalse()
            ->and($input->filled(['name', 'votes']))->toBeFalse()
            ->and($input->filled(['votes', 'foo.bar']))->toBeFalse();
    });

    test('isNotFilled requires every key to be empty', function () {
        $input = presenceInput();

        expect($input->isNotFilled('name'))->toBeFalse()
            ->and($input->isNotFilled('surname'))->toBeFalse()
            ->and($input->isNotFilled(['name', 'surname']))->toBeFalse()
            ->and($input->isNotFilled(['name', 'foo']))->toBeFalse()
            ->and($input->isNotFilled('foo'))->toBeFalse()
            ->and($input->isNotFilled(['name', 'foo.baz']))->toBeFalse()
            ->and($input->isNotFilled(['name', 'votes']))->toBeFalse()
            ->and($input->isNotFilled('foo.bar'))->toBeTrue()
            ->and($input->isNotFilled('votes'))->toBeTrue()
            ->and($input->isNotFilled(['votes', 'foo.bar']))->toBeTrue();
    });

    test('anyFilled requires only one key to hold a non-empty value', function () {
        $input = presenceInput();

        expect($input->anyFilled('name'))->toBeTrue()
            ->and($input->anyFilled('surname'))->toBeTrue()
            ->and($input->anyFilled(['name', 'surname']))->toBeTrue()
            ->and($input->anyFilled(['name', 'foo']))->toBeTrue()
            ->and($input->anyFilled('foo'))->toBeTrue()
            ->and($input->anyFilled(['name', 'foo.baz']))->toBeTrue()
            ->and($input->anyFilled(['name', 'votes']))->toBeTrue()
            ->and($input->anyFilled('foo.bar'))->toBeFalse()
            ->and($input->anyFilled('votes'))->toBeFalse()
            ->and($input->anyFilled(['votes', 'foo.bar']))->toBeFalse();
    });
});

describe('conditional callbacks', function () {
    test('whenHas runs on presence, whatever the value', function () {
        $input = new ValidatedInput(['name' => 'Fatih', 'age' => '', 'foo' => ['bar' => null]]);

        $name = $age = $city = $foo = $bar = $baz = false;

        $input->whenHas('name', function ($value) use (&$name) {
            $name = $value;
        });

        $input->whenHas('age', function ($value) use (&$age) {
            $age = $value;
        });

        $input->whenHas('city', function ($value) use (&$city) {
            $city = $value;
        });

        $input->whenHas('foo', function ($value) use (&$foo) {
            $foo = $value;
        });

        $input->whenHas('foo.bar', function ($value) use (&$bar) {
            $bar = $value;
        });

        $input->whenHas('foo.baz', function () use (&$baz) {
            $baz = 'test';
        }, function () use (&$baz) {
            $baz = true;
        });

        expect($name)->toBe('Fatih')
            ->and($age)->toBe('')
            ->and($city)->toBeFalse()
            ->and($foo)->toEqual(['bar' => null])
            ->and($baz)->toBeTrue()
            ->and($bar)->toBeNull();
    });

    test('whenFilled runs only for non-empty values', function () {
        $input = new ValidatedInput(['name' => 'Fatih', 'age' => '', 'foo' => ['bar' => null]]);

        $name = $age = $city = $foo = $bar = $baz = false;

        $input->whenFilled('name', function ($value) use (&$name) {
            $name = $value;
        });

        $input->whenFilled('age', function ($value) use (&$age) {
            $age = $value;
        });

        $input->whenFilled('city', function ($value) use (&$city) {
            $city = $value;
        });

        $input->whenFilled('foo', function ($value) use (&$foo) {
            $foo = $value;
        });

        $input->whenFilled('foo.bar', function ($value) use (&$bar) {
            $bar = $value;
        });

        $input->whenFilled('foo.baz', function () use (&$baz) {
            $baz = 'test';
        }, function () use (&$baz) {
            $baz = true;
        });

        expect($name)->toBe('Fatih')
            ->and($foo)->toEqual(['bar' => null])
            ->and($baz)->toBeTrue()
            ->and($age)->toBeFalse()
            ->and($city)->toBeFalse()
            ->and($bar)->toBeFalse();
    });

    test('whenMissing runs on absence', function () {
        $input = new ValidatedInput(['foo' => ['bar' => null]]);

        $name = $age = $city = $foo = $bar = $baz = false;

        $input->whenMissing('name', function () use (&$name) {
            $name = 'Fatih';
        });

        $input->whenMissing('age', function () use (&$age) {
            $age = '';
        });

        $input->whenMissing('city', function () use (&$city) {
            $city = null;
        });

        $input->whenMissing('foo', function ($value) use (&$foo) {
            $foo = $value;
        });

        $input->whenMissing('foo.baz', function () use (&$baz) {
            $baz = true;
        });

        $input->whenMissing('foo.bar', function () use (&$bar) {
            $bar = 'test';
        }, function () use (&$bar) {
            $bar = true;
        });

        expect($name)->toBe('Fatih')
            ->and($age)->toBe('')
            ->and($city)->toBeNull()
            ->and($foo)->toBeFalse()
            ->and($baz)->toBeTrue()
            ->and($bar)->toBeTrue();
    });
});

describe('typed accessors', function () {
    test('str returns a Stringable, casting as it goes', function () {
        $input = stringyInput();

        expect($input->str('int'))->toBeInstanceOf(Stringable::class)
            ->and($input->str('unknown_key'))->toBeInstanceOf(Stringable::class)
            ->and($input->str('int')->value())->toBe('123')
            ->and($input->str('int_str')->value())->toBe('456')
            ->and($input->str('float')->value())->toBe('123.456')
            ->and($input->str('float_str')->value())->toBe('123.456')
            ->and($input->str('float_zero')->value())->toBe('0')
            ->and($input->str('float_str_zero')->value())->toBe('0.000')
            ->and($input->str('empty_str')->value())->toBe('')
            ->and($input->str('null')->value())->toBe('')
            ->and($input->str('unknown_key')->value())->toBe('');
    });

    test('string behaves the same as str', function () {
        $input = stringyInput();

        expect($input->string('int'))->toBeInstanceOf(Stringable::class)
            ->and($input->string('unknown_key'))->toBeInstanceOf(Stringable::class)
            ->and($input->string('int')->value())->toBe('123')
            ->and($input->string('int_str')->value())->toBe('456')
            ->and($input->string('float')->value())->toBe('123.456')
            ->and($input->string('float_str')->value())->toBe('123.456')
            ->and($input->string('float_zero')->value())->toBe('0')
            ->and($input->string('float_str_zero')->value())->toBe('0.000')
            ->and($input->string('empty_str')->value())->toBe('')
            ->and($input->string('null')->value())->toBe('')
            ->and($input->string('unknown_key')->value())->toBe('');
    });

    test('boolean reads the usual truthy strings', function () {
        $input = new ValidatedInput([
            'with_trashed' => 'false',
            'download' => true,
            'checked' => 1,
            'unchecked' => '0',
            'with_on' => 'on',
            'with_yes' => 'yes',
        ]);

        expect($input->boolean('checked'))->toBeTrue()
            ->and($input->boolean('download'))->toBeTrue()
            ->and($input->boolean('unchecked'))->toBeFalse()
            ->and($input->boolean('with_trashed'))->toBeFalse()
            ->and($input->boolean('some_undefined_key'))->toBeFalse()
            ->and($input->boolean('with_on'))->toBeTrue()
            ->and($input->boolean('with_yes'))->toBeTrue();
    });

    test('integer casts to int and honours the default', function () {
        $input = new ValidatedInput([
            'int' => '123',
            'raw_int' => 456,
            'zero_padded' => '078',
            'space_padded' => ' 901',
            'nan' => 'nan',
            'mixed' => '1ab',
            'underscore_notation' => '2_000',
            'null' => null,
        ]);

        expect($input->integer('int'))->toBe(123)
            ->and($input->integer('raw_int'))->toBe(456)
            ->and($input->integer('zero_padded'))->toBe(78)
            ->and($input->integer('space_padded'))->toBe(901)
            ->and($input->integer('nan'))->toBe(0)
            ->and($input->integer('mixed'))->toBe(1)
            ->and($input->integer('underscore_notation'))->toBe(2)
            ->and($input->integer('unknown_key', 123456))->toBe(123456)
            ->and($input->integer('null'))->toBe(0)
            ->and($input->integer('null', 123456))->toBe(0);
    });

    test('float casts to float and honours the default', function () {
        $input = new ValidatedInput([
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

        expect($input->float('float'))->toBe(1.23)
            ->and($input->float('raw_float'))->toBe(45.6)
            ->and($input->float('decimal_only'))->toBe(.6)
            ->and($input->float('zero_padded'))->toBe(0.78)
            ->and($input->float('space_padded'))->toBe(90.1)
            ->and($input->float('nan'))->toBe(0.0)
            ->and($input->float('mixed'))->toBe(1.0)
            ->and($input->float('scientific_notation'))->toBe(1e3)
            ->and($input->float('unknown_key', 123.456))->toBe(123.456)
            ->and($input->float('null'))->toBe(0.0)
            ->and($input->float('null', 123.456))->toBe(0.0);
    });

    test('date parses the value, with an optional format and timezone', function () {
        $input = new ValidatedInput([
            'as_null' => null,
            'as_invalid' => 'invalid',

            'as_datetime' => '24-01-01 16:30:25',
            'as_format' => '1704126625',
            'as_timezone' => '24-01-01 13:30:25',

            'as_date' => '2024-01-01',
            'as_time' => '16:30:25',
        ]);

        $current = Carbon::create(2024, 1, 1, 16, 30, 25);

        expect($input->date('as_null'))->toBeNull()
            ->and($input->date('doesnt_exists'))->toBeNull()
            ->and($input->date('as_datetime'))->toEqual($current)
            ->and($input->date('as_format', 'U')->format('Y-m-d H:i:s P'))->toEqual($current->format('Y-m-d H:i:s P'))
            ->and($input->date('as_timezone', null, 'America/Santiago'))->toEqual($current)
            ->and($input->date('as_date')->isSameDay($current))->toBeTrue()
            ->and($input->date('as_time')->isSameSecond('16:30:25'))->toBeTrue();
    });

    test('enum resolves a backed enum, or null', function () {
        $input = new ValidatedInput([
            'valid_enum_value' => 'Hello world',
            'invalid_enum_value' => 'invalid',
        ]);

        expect($input->enum('doesnt_exists', StringBackedEnum::class))->toBeNull()
            ->and($input->enum('valid_enum_value', StringBackedEnum::class))->toEqual(StringBackedEnum::HELLO_WORLD)
            ->and($input->enum('invalid_enum_value', StringBackedEnum::class))->toBeNull();
    });

    test('enums resolves a list of backed enums, dropping the invalid ones', function () {
        $input = new ValidatedInput([
            'valid_enum_value' => 'Hello world',
            'invalid_enum_value' => 'invalid',
        ]);

        expect($input->enums('doesnt_exists', StringBackedEnum::class))->toBeEmpty()
            ->and($input->enums('valid_enum_value', StringBackedEnum::class))->toEqual([StringBackedEnum::HELLO_WORLD])
            ->and($input->enums('invalid_enum_value', StringBackedEnum::class))->toBeEmpty();
    });

    test('collect returns Collections of the whole bag or a subset', function () {
        $input = new ValidatedInput(['users' => [1, 2, 3]]);

        expect($input->collect('users'))->toBeInstanceOf(Collection::class)
            ->and($input->collect('developers')->isEmpty())->toBeTrue()
            ->and($input->collect('users')->all())->toEqual([1, 2, 3])
            ->and($input->collect()->all())->toEqual(['users' => [1, 2, 3]]);

        expect((new ValidatedInput(['text-payload']))->collect()->all())->toEqual(['text-payload']);
        expect((new ValidatedInput(['email' => 'test@example.com']))->collect('email')->all())->toEqual(['test@example.com']);

        $input = new ValidatedInput([]);

        expect($input->collect())->toBeInstanceOf(Collection::class)
            ->and($input->collect()->isEmpty())->toBeTrue();

        $input = new ValidatedInput(['users' => [1, 2, 3], 'roles' => [4, 5, 6], 'foo' => ['bar', 'baz'], 'email' => 'test@example.com']);

        expect($input->collect(['users']))->toBeInstanceOf(Collection::class)
            ->and($input->collect(['developers'])->isEmpty())->toBeTrue()
            ->and($input->collect(['roles'])->isNotEmpty())->toBeTrue()
            ->and($input->collect(['roles'])->all())->toEqual(['roles' => [4, 5, 6]])
            ->and($input->collect(['users', 'email'])->all())->toEqual(['users' => [1, 2, 3], 'email' => 'test@example.com'])
            ->and($input->collect(['roles', 'foo']))->toEqual(collect(['roles' => [4, 5, 6], 'foo' => ['bar', 'baz']]))
            ->and($input->collect()->all())->toEqual(['users' => [1, 2, 3], 'roles' => [4, 5, 6], 'foo' => ['bar', 'baz'], 'email' => 'test@example.com']);
    });
});
