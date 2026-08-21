<?php

use Voyager\NutsAndBolts\LazyCollection;

describe('sources', function () {
    test('make accepts a generator function', function () {
        $lazy = LazyCollection::make(function () {
            yield 1;
            yield 2;
        });

        expect($lazy)->toBeInstanceOf(LazyCollection::class)
            ->and($lazy->all())->toBe([1, 2]);
    });

    test('make accepts an array and an empty source', function () {
        expect(LazyCollection::make([1, 2, 3])->all())->toBe([1, 2, 3])
            ->and(LazyCollection::make()->all())->toBe([]);
    });

    test('the constructor accepts a generator function too', function () {
        expect((new LazyCollection(fn () => yield 5))->all())->toBe([5]);
    });

    test('a generator object is rejected in favour of a generator function', function () {
        LazyCollection::make((function () {
            yield 1;
        })());
    })->throws(InvalidArgumentException::class);
});

describe('laziness', function () {
    test('take short-circuits an infinite source', function () {
        $lazy = LazyCollection::make(function () {
            $i = 0;
            while (true) {
                yield $i++;
            }
        });

        expect($lazy->take(4)->all())->toBe([0, 1, 2, 3]);
    });

    test('only the consumed prefix of the source is evaluated', function () {
        $produced = 0;

        $lazy = LazyCollection::make(function () use (&$produced) {
            foreach (range(1, 100) as $value) {
                $produced++;
                yield $value;
            }
        });

        $lazy->take(3)->all();

        expect($produced)->toBe(3);
    });

    test('takeWhile stops at the first failure', function () {
        expect(LazyCollection::make([1, 2, 9, 3])->takeWhile(fn (int $v) => $v < 5)->all())->toBe([1, 2]);
    });
});

describe('pipeline', function () {
    test('map and filter compose lazily', function () {
        $result = LazyCollection::make(function () {
            yield from [1, 2, 3, 4];
        })->filter(fn (int $v) => $v % 2 === 0)->map(fn (int $v) => $v * 10);

        expect($result->values()->all())->toBe([20, 40]);
    });

    test('a lazy collection converts to an eager one', function () {
        expect(LazyCollection::make([1, 2])->collect()->all())->toBe([1, 2]);
    });

    test('chunk batches the stream, preserving keys by default', function () {
        expect(LazyCollection::make([1, 2, 3, 4, 5])->chunk(2)->map->all()->all())
            ->toBe([[1, 2], [2 => 3, 3 => 4], [4 => 5]]);
    });

    test('chunk can discard keys', function () {
        expect(LazyCollection::make([1, 2, 3, 4, 5])->chunk(2, false)->map->all()->all())
            ->toBe([[1, 2], [3, 4], [5]]);
    });
});
