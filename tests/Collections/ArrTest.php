<?php

use Voyager\NutsAndBolts\DataObjects\Arr;

describe('dot notation', function () {
    test('get retrieves nested values', function () {
        $array = ['app' => ['name' => 'Venusian']];

        expect(Arr::get($array, 'app.name'))->toBe('Venusian')
            ->and(Arr::get($array, 'app.missing', 'fallback'))->toBe('fallback');
    });

    test('get returns default for missing keys', function () {
        expect(Arr::get([], 'missing'))->toBeNull()
            ->and(Arr::get([], 'missing', 'default'))->toBe('default');
    });

    test('has reports presence', function () {
        $array = ['app' => ['name' => 'Venusian']];

        expect(Arr::has($array, 'app.name'))->toBeTrue()
            ->and(Arr::has($array, 'app.missing'))->toBeFalse();
    });

    test('set writes nested values', function () {
        $array = [];

        Arr::set($array, 'app.name', 'Venusian');

        expect($array)->toBe(['app' => ['name' => 'Venusian']]);
    });

    test('forget removes nested values', function () {
        $array = ['app' => ['name' => 'Venusian', 'env' => 'cli']];

        Arr::forget($array, 'app.env');

        expect($array)->toBe(['app' => ['name' => 'Venusian']]);
    });

    test('dot flattens and undot restores', function () {
        $nested = ['app' => ['name' => 'Venusian']];

        expect(Arr::dot($nested))->toBe(['app.name' => 'Venusian'])
            ->and(Arr::undot(['app.name' => 'Venusian']))->toBe($nested);
    });
});

describe('selection', function () {
    test('first and last respect a callback', function () {
        $array = [1, 2, 3, 4];

        expect(Arr::first($array))->toBe(1)
            ->and(Arr::last($array))->toBe(4)
            ->and(Arr::first($array, fn (int $v) => $v > 2))->toBe(3)
            ->and(Arr::last($array, fn (int $v) => $v < 3))->toBe(2);
    });

    test('first returns the default when nothing matches', function () {
        expect(Arr::first([1, 2], fn (int $v) => $v > 10, 'none'))->toBe('none');
    });

    test('only and except filter by key', function () {
        $array = ['name' => 'Venusian', 'version' => '0.8.0', 'secret' => 'x'];

        expect(Arr::only($array, ['name']))->toBe(['name' => 'Venusian'])
            ->and(Arr::except($array, ['secret']))->toBe(['name' => 'Venusian', 'version' => '0.8.0']);
    });

    test('pluck extracts a column', function () {
        $records = [['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']];

        expect(Arr::pluck($records, 'name'))->toBe(['a', 'b'])
            ->and(Arr::pluck($records, 'name', 'id'))->toBe([1 => 'a', 2 => 'b']);
    });
});

describe('shaping', function () {
    test('flatten collapses nesting', function () {
        expect(Arr::flatten([1, [2, [3, [4]]]]))->toBe([1, 2, 3, 4])
            ->and(Arr::flatten([1, [2, [3]]], 1))->toBe([1, 2, [3]]);
    });

    test('wrap normalises any value to an array', function (mixed $value, array $expected) {
        expect(Arr::wrap($value))->toBe($expected);
    })->with([
        'null'   => [null, []],
        'scalar' => ['a', ['a']],
        'array'  => [['a'], ['a']],
    ]);
});
