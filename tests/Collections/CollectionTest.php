<?php

use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\Exceptions\ItemNotFoundException;
use Voyager\NutsAndBolts\Exceptions\MultipleItemsFoundException;

describe('construction', function () {
    test('collect creates a collection from an array', function () {
        $collection = collect([1, 2, 3]);

        expect($collection)->toBeInstanceOf(Collection::class)
            ->and($collection->all())->toBe([1, 2, 3]);
    });

    test('make and empty are equivalent entry points', function () {
        expect(Collection::make([1, 2])->all())->toBe([1, 2])
            ->and(Collection::empty()->all())->toBe([]);
    });

    test('times builds from a generator callback', function () {
        expect(Collection::times(3, fn (int $n) => $n * 2)->all())->toBe([2, 4, 6]);
    });
});

describe('transformation', function () {
    test('map transforms items', function () {
        expect(collect([1, 2, 3])->map(fn (int $v) => $v * 2)->all())->toBe([2, 4, 6]);
    });

    test('filter keeps matching items', function () {
        expect(collect([1, 2, 3, 4])->filter(fn (int $v) => $v % 2 === 0)->values()->all())->toBe([2, 4]);
    });

    test('reject is the inverse of filter', function () {
        expect(collect([1, 2, 3, 4])->reject(fn (int $v) => $v % 2 === 0)->values()->all())->toBe([1, 3]);
    });

    test('flatMap flattens one level', function () {
        expect(collect([[1, 2], [3]])->flatMap(fn (array $v) => $v)->all())->toBe([1, 2, 3]);
    });

    test('mapWithKeys rebuilds the keys', function () {
        $result = collect([['id' => 1, 'name' => 'a']])
            ->mapWithKeys(fn (array $row) => [$row['id'] => $row['name']]);

        expect($result->all())->toBe([1 => 'a']);
    });

    test('pluck extracts a column', function () {
        expect(collect([['n' => 'a'], ['n' => 'b']])->pluck('n')->all())->toBe(['a', 'b']);
    });
});

describe('reduction', function () {
    test('sum, avg, min and max', function () {
        $collection = collect([1, 2, 3, 4]);

        expect($collection->sum())->toBe(10)
            ->and($collection->avg())->toBe(2.5)
            ->and($collection->min())->toBe(1)
            ->and($collection->max())->toBe(4);
    });

    test('reduce threads an accumulator', function () {
        expect(collect([1, 2, 3])->reduce(fn (?int $carry, int $v) => $carry + $v, 0))->toBe(6);
    });

    test('implode joins values', function () {
        expect(collect(['a', 'b'])->implode(', '))->toBe('a, b');
    });

    test('implode joins a column of arrays', function () {
        expect(collect([['n' => 'a'], ['n' => 'b']])->implode('n', ', '))->toBe('a, b');
    });
});

describe('grouping and lookup', function () {
    test('groupBy buckets by key', function () {
        $result = collect([
            ['team' => 'red', 'name' => 'a'],
            ['team' => 'blue', 'name' => 'b'],
            ['team' => 'red', 'name' => 'c'],
        ])->groupBy('team');

        expect($result->keys()->all())->toBe(['red', 'blue'])
            ->and($result->get('red')->count())->toBe(2);
    });

    test('keyBy rekeys the collection', function () {
        expect(collect([['id' => 7, 'n' => 'a']])->keyBy('id')->keys()->all())->toBe([7]);
    });

    test('where filters on a column', function () {
        expect(collect([['k' => 'a'], ['k' => 'b']])->where('k', 'a')->count())->toBe(1);
    });

    test('firstWhere returns the first match', function () {
        expect(collect([['k' => 'a'], ['k' => 'b']])->firstWhere('k', 'b'))->toBe(['k' => 'b']);
    });

    test('sole returns the only match', function () {
        expect(collect([1, 2, 3])->sole(fn (int $v) => $v === 2))->toBe(2);
    });

    test('sole throws when there is no match', function () {
        collect([1, 2])->sole(fn (int $v) => $v === 9);
    })->throws(ItemNotFoundException::class);

    test('sole throws when there are several matches', function () {
        collect([1, 1])->sole(fn (int $v) => $v === 1);
    })->throws(MultipleItemsFoundException::class);
});

describe('ordering', function () {
    test('sort and sortBy', function () {
        expect(collect([3, 1, 2])->sort()->values()->all())->toBe([1, 2, 3])
            ->and(collect([['n' => 2], ['n' => 1]])->sortBy('n')->values()->pluck('n')->all())->toBe([1, 2]);
    });

    test('reverse flips order', function () {
        expect(collect([1, 2, 3])->reverse()->values()->all())->toBe([3, 2, 1]);
    });
});

describe('serialisation', function () {
    test('toArray unwraps nested arrayables', function () {
        expect(collect(['a' => collect([1])])->toArray())->toBe(['a' => [1]]);
    });

    test('toJson encodes', function () {
        expect(collect(['a' => 1])->toJson())->toBe('{"a":1}');
    });

    test('a collection is countable and iterable', function () {
        $collection = collect([1, 2, 3]);

        expect($collection)->toHaveCount(3)
            ->and(iterator_to_array($collection))->toBe([1, 2, 3]);
    });
});

describe('array access', function () {
    test('offsets can be read, written and removed', function () {
        $collection = collect(['a' => 1]);

        expect(isset($collection['a']))->toBeTrue()
            ->and($collection['a'])->toBe(1);

        $collection['b'] = 2;
        expect($collection->all())->toBe(['a' => 1, 'b' => 2]);

        unset($collection['a']);
        expect($collection->all())->toBe(['b' => 2]);
    });

    test('higher order proxies forward to map', function () {
        $collection = collect([collect([1, 2]), collect([3])]);

        expect($collection->map->count()->all())->toBe([2, 1]);
    });
});
