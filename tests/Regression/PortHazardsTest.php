<?php

use Voyager\NutsAndBolts\Concerns\ReflectsClosures;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\LazyCollection;

/*
|--------------------------------------------------------------------------
| Port hazards
|--------------------------------------------------------------------------
|
| Defects found on 2026-08-19 in code ported from Venusian. Most share one
| cause: PHP type hints were added to signatures Venusian leaves untyped, so a
| stringable argument was silently coerced instead of rejected. The failure
| mode was a wrong answer, never an exception — which is why these need tests.
|
*/

describe('native \Stringable is honoured in Collections', function () {
    test('implode joins native stringables rather than returning empty', function () {
        $collection = collect([nativeStringable('a'), nativeStringable('b')]);

        expect($collection->implode(', '))->toBe('a, b');
    });

    test('groupBy casts a native stringable key rather than throwing', function () {
        $result = collect(['x' => 1])->groupBy(fn () => nativeStringable('g'));

        expect($result->keys()->all())->toBe(['g']);
    });

    test('where compares against a native stringable', function () {
        $result = collect([['k' => 'a'], ['k' => 'b']])->where('k', nativeStringable('a'));

        expect($result)->toHaveCount(1);
    });

    test("Voyager's own Stringable still works, being a native \Stringable", function () {
        expect(collect([Str::of('a'), Str::of('b')])->implode(', '))->toBe('a, b');
    });
});

describe('Str accepts any iterable of needles, not just arrays', function () {
    test('a Collection of needles is iterated, not cast to its JSON form', function (string $method, array $args, mixed $expected) {
        $withArray = Str::{$method}(...$args);

        $iterableArgs = array_map(
            fn (mixed $arg) => is_array($arg) ? collect($arg) : $arg,
            $args,
        );

        expect($withArray)->toBe($expected)
            ->and(Str::{$method}(...$iterableArgs))->toBe($expected);
    })->with([
        'is'            => ['is', [['x*', 'a*'], 'abc'], true],
        'isMatch'       => ['isMatch', [['/x/', '/a/'], 'abc'], true],
        'contains'      => ['contains', ['abc', ['x', 'b']], true],
        'containsAll'   => ['containsAll', ['abc', ['a', 'b']], true],
        'startsWith'    => ['startsWith', ['abc', ['x', 'a']], true],
        'endsWith'      => ['endsWith', ['abc', ['x', 'c']], true],
        'doesntContain' => ['doesntContain', ['abc', ['z']], true],
        'chopStart'     => ['chopStart', ['abc', ['a']], 'bc'],
        'chopEnd'       => ['chopEnd', ['abc', ['c']], 'ab'],
        'remove'        => ['remove', [['a'], 'abc'], 'bc'],
    ]);

    test('a generator of patterns is accepted', function () {
        $patterns = (function () {
            yield 'x*';
            yield 'a*';
        })();

        expect(Str::is($patterns, 'abc'))->toBeTrue();
    });

    test('an ArrayObject of needles is accepted', function () {
        expect(Str::contains('abc', new ArrayObject(['x', 'b'])))->toBeTrue();
    });

    test('chop helpers do not object-cast their needles', function () {
        expect(Str::chopStart('foobar', collect(['foo'])))->toBe('bar')
            ->and(Str::chopEnd('foobar', collect(['bar'])))->toBe('foo');
    });
});

describe('members that were missing or mis-declared', function () {
    test('Str::singular exists and matches the fluent form', function () {
        expect(Str::singular('users'))->toBe('user')
            ->and(Str::of('users')->singular()->value())->toBe('user');
    });

    test('LazyCollection::make accepts the Closure its constructor accepts', function () {
        expect(LazyCollection::make(fn () => yield from [1, 2])->all())
            ->toBe((new LazyCollection(fn () => yield from [1, 2]))->all());
    });

    test('ReflectsClosures is a trait, so it can be composed', function () {
        expect(trait_exists(ReflectsClosures::class))->toBeTrue();
    });
});

describe('the time helpers load and resolve', function () {
    test('now resolves without the absent MagicAliases Date class', function () {
        expect(now())->toBeInstanceOf(Carbon::class);
    });

    test('the helper guards name the global functions they declare', function (string $function) {
        // time.php has no namespace declaration, so it declares global functions.
        // Its guards originally tested for namespaced names and could never fire.
        expect(function_exists($function))->toBeTrue();

        $source = file_get_contents(__DIR__.'/../../src/Voyager/NutsAndBolts/Helpers/time.php');

        expect($source)->toContain("function_exists('{$function}')")
            ->and($source)->not->toContain("function_exists('Voyager\\NutsAndBolts\\{$function}')");
    })->with(['now', 'seconds', 'minutes', 'hours', 'days', 'weeks', 'months', 'years']);
});
