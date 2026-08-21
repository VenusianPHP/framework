<?php

use Voyager\Vessel\Util;

test('unwrapIfClosure returns plain values untouched and invokes closures', function () {
    expect(Util::unwrapIfClosure('foo'))->toBe('foo')
        ->and(Util::unwrapIfClosure(fn () => 'foo'))->toBe('foo');
});

describe('arrayWrap', function () {
    test('wraps scalars, leaves arrays alone and maps null to an empty array', function (mixed $value, array $expected) {
        expect(Util::arrayWrap($value))->toEqual($expected);
    })->with(function () {
        $object = new stdClass;
        $object->value = 'a';

        return [
            'string'        => ['a', ['a']],
            'array'         => [['a'], ['a']],
            'object'        => [$object, [$object]],
            'null'          => [null, []],
            'array of null' => [[null], [null]],
            'array of nulls' => [[null, null], [null, null]],
            'empty string'  => ['', ['']],
            'array of empty string' => [[''], ['']],
            'false'         => [false, [false]],
            'array of false' => [[false], [false]],
            'zero'          => [0, [0]],
        ];
    });

    test('preserves object identity', function () {
        $object = new stdClass;
        $object->value = 'a';
        $object = unserialize(serialize($object));

        expect(Util::arrayWrap($object))->toEqual([$object])
            ->and(Util::arrayWrap($object)[0])->toBe($object);
    });
});

describe('getParameterClassName', function () {
    test('returns the class name for a class-typed parameter', function () {
        $parameter = new ReflectionParameter(function (stdClass $foo) {}, 0);

        expect(Util::getParameterClassName($parameter))->toBe('stdClass');
    });

    test('returns null for a scalar-typed parameter', function () {
        $parameter = new ReflectionParameter(function (string $foo) {}, 0);

        expect(Util::getParameterClassName($parameter))->toBeNull();
    });
});
