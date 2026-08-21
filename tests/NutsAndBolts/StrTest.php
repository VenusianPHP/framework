<?php

use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\DataObjects\Stringable;

describe('case conversion', function () {
    test('converts between cases', function (string $method, string $input, string $expected) {
        expect(Str::{$method}($input))->toBe($expected);
    })->with([
        'camel'   => ['camel', 'foo_bar', 'fooBar'],
        'snake'   => ['snake', 'fooBar', 'foo_bar'],
        'kebab'   => ['kebab', 'fooBar', 'foo-bar'],
        'studly'  => ['studly', 'foo_bar', 'FooBar'],
        'pascal'  => ['pascal', 'foo_bar', 'FooBar'],
        'title'   => ['title', 'a nice title', 'A Nice Title'],
        'lower'   => ['lower', 'ABC', 'abc'],
        'upper'   => ['upper', 'abc', 'ABC'],
        'ucfirst' => ['ucfirst', 'hello', 'Hello'],
        'lcfirst' => ['lcfirst', 'Hello', 'hello'],
    ]);

    test('headline titlecases mixed separators', function () {
        expect(Str::headline('steve_jobs bio'))->toBe('Steve Jobs Bio');
    });
});

describe('slugs and trimming', function () {
    test('slug lowercases and joins with a separator', function () {
        expect(Str::slug('Hello Venusian World'))->toBe('hello-venusian-world')
            ->and(Str::slug('Hello World', '_'))->toBe('hello_world');
    });

    test('squish collapses whitespace', function () {
        expect(Str::squish('  a   b  '))->toBe('a b');
    });

    test('trim variants strip whitespace', function () {
        expect(Str::trim('  a  '))->toBe('a')
            ->and(Str::ltrim('  a'))->toBe('a')
            ->and(Str::rtrim('a  '))->toBe('a');
    });
});

describe('search', function () {
    test('contains, startsWith and endsWith accept a single needle', function () {
        expect(Str::contains('abc', 'b'))->toBeTrue()
            ->and(Str::startsWith('abc', 'a'))->toBeTrue()
            ->and(Str::endsWith('abc', 'c'))->toBeTrue()
            ->and(Str::doesntContain('abc', 'z'))->toBeTrue();
    });

    test('containsAll requires every needle', function () {
        expect(Str::containsAll('abc', ['a', 'b']))->toBeTrue()
            ->and(Str::containsAll('abc', ['a', 'z']))->toBeFalse();
    });

    test('is matches wildcard patterns', function (string|array $pattern, string $value, bool $expected) {
        expect(Str::is($pattern, $value))->toBe($expected);
    })->with([
        'exact'            => ['foo', 'foo', true],
        'prefix wildcard'  => ['foo*', 'foobar', true],
        'bare wildcard'    => ['*', 'anything', true],
        'path wildcard'    => ['foo/*', 'foo/bar/baz', true],
        'no match'         => ['baz*', 'foobar', false],
        'case sensitive'   => ['foo', 'FOO', false],
        'later array item' => [['nope*', 'foo*'], 'foobar', true],
    ]);

    test('is honours the ignoreCase flag', function () {
        expect(Str::is('FOO*', 'foobar', ignoreCase: true))->toBeTrue()
            ->and(Str::is('FOO*', 'foobar'))->toBeFalse();
    });

    test('isMatch applies a regular expression', function () {
        expect(Str::isMatch('/a/', 'abc'))->toBeTrue()
            ->and(Str::isMatch('/z/', 'abc'))->toBeFalse();
    });

    test('match returns the first capture group when present', function () {
        expect(Str::match('/b(.)d/', 'abcd'))->toBe('c')
            ->and(Str::match('/bcd/', 'abcd'))->toBe('bcd');
    });
});

describe('substrings', function () {
    test('before, after and between slice around markers', function () {
        expect(Str::before('a-b-c', '-'))->toBe('a')
            ->and(Str::beforeLast('a-b-c', '-'))->toBe('a-b')
            ->and(Str::after('a-b-c', '-'))->toBe('b-c')
            ->and(Str::afterLast('a-b-c', '-'))->toBe('c')
            ->and(Str::between('[a]', '[', ']'))->toBe('a');
    });

    test('limit truncates with an ellipsis', function () {
        expect(Str::limit('The quick brown fox', 9))->toBe('The quick...')
            ->and(Str::limit('short', 100))->toBe('short');
    });

    test('words truncates by word count', function () {
        expect(Str::words('The quick brown fox', 2))->toBe('The quick...');
    });

    test('excerpt centres on a phrase', function () {
        expect(Str::excerpt('This is my name', 'my', ['radius' => 3]))->toBe('...is my na...');
    });

    test('mask hides a span', function () {
        expect(Str::mask('1234567890', '*', 3))->toBe('123*******');
    });

    test('chopStart and chopEnd remove affixes', function () {
        expect(Str::chopStart('foobar', 'foo'))->toBe('bar')
            ->and(Str::chopEnd('foobar', 'bar'))->toBe('foo')
            ->and(Str::chopStart('foobar', ['x', 'foo']))->toBe('bar');
    });
});

describe('replacement', function () {
    test('replace swaps single and paired values', function () {
        expect(Str::replace('a', 'z', 'abc'))->toBe('zbc')
            ->and(Str::replace(['a', 'b'], ['z', 'y'], 'abc'))->toBe('zyc');
    });

    test('remove deletes needles', function () {
        expect(Str::remove('a', 'abc'))->toBe('bc');
    });

    test('replaceFirst and replaceLast are positional', function () {
        expect(Str::replaceFirst('a', 'z', 'aba'))->toBe('zba')
            ->and(Str::replaceLast('a', 'z', 'aba'))->toBe('abz');
    });

    test('swap applies a map', function () {
        expect(Str::swap(['a' => 'b'], 'a'))->toBe('b');
    });
});

describe('inflection', function () {
    test('plural and singular handle regular nouns', function () {
        expect(Str::plural('user'))->toBe('users')
            ->and(Str::plural('category'))->toBe('categories')
            ->and(Str::singular('users'))->toBe('user')
            ->and(Str::singular('categories'))->toBe('category');
    });

    test('plural respects a count of one', function () {
        expect(Str::plural('user', 1))->toBe('user')
            ->and(Str::plural('user', 2))->toBe('users');
    });

    test('pluralStudly keeps the studly casing', function () {
        expect(Str::pluralStudly('UserGroup'))->toBe('UserGroups');
    });
});

describe('identifiers', function () {
    test('uuid generates a valid uuid', function () {
        expect(Str::isUuid((string) Str::uuid()))->toBeTrue();
    });

    test('ulid generates a valid ulid', function () {
        expect(Str::isUlid((string) Str::ulid()))->toBeTrue();
    });

    test('frozen uuids repeat within the callback', function () {
        $seen = Str::freezeUuids(function ($uuid) {
            expect((string) Str::uuid())->toBe((string) $uuid);
        });

        expect((string) Str::uuid())->not->toBe((string) $seen);
    });

    test('random produces the requested length', function () {
        expect(Str::random(24))->toHaveLength(24);
    });
});

describe('predicates', function () {
    test('recognises well-formed values', function () {
        expect(Str::isJson('{"a":1}'))->toBeTrue()
            ->and(Str::isJson('nope'))->toBeFalse()
            ->and(Str::isUrl('https://example.com'))->toBeTrue()
            ->and(Str::isUrl('nope'))->toBeFalse()
            ->and(Str::isAscii('abc'))->toBeTrue()
            ->and(Str::isAscii('ü'))->toBeFalse();
    });
});

describe('encoding', function () {
    test('base64 round-trips', function () {
        expect(Str::toBase64('abc'))->toBe('YWJj')
            ->and(Str::fromBase64('YWJj'))->toBe('abc');
    });

    test('ascii transliterates', function () {
        expect(Str::ascii('ü'))->toBe('u');
    });
});

describe('fluent entry point', function () {
    test('of returns a fluent stringable', function () {
        $stringable = Str::of('foo_bar');

        expect($stringable)->toBeInstanceOf(Stringable::class)
            ->and($stringable->camel()->value())->toBe('fooBar')
            ->and($stringable->snake()->value())->toBe('foo_bar');
    });
});
