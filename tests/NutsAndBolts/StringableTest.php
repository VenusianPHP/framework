<?php

use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\DataObjects\Stringable;

test('a stringable wraps and unwraps a value', function () {
    $stringable = Str::of('venusian');

    expect((string) $stringable)->toBe('venusian')
        ->and($stringable->value())->toBe('venusian')
        ->and($stringable->toString())->toBe('venusian');
});

test('calls chain fluently', function () {
    expect(Str::of('  foo_bar  ')->trim()->camel()->upper()->value())->toBe('FOOBAR');
});

test('append and prepend extend the value', function () {
    expect(Str::of('b')->prepend('a')->append('c')->value())->toBe('abc');
});

test('conditionals apply inline', function () {
    expect(Str::of('a')->when(true, fn (Stringable $s) => $s->append('b'))->value())->toBe('ab')
        ->and(Str::of('a')->when(false, fn (Stringable $s) => $s->append('b'))->value())->toBe('a')
        ->and(Str::of('a')->unless(true, fn (Stringable $s) => $s->append('b'))->value())->toBe('a');
});

test('explode and split produce collections', function () {
    expect(Str::of('a,b')->explode(','))->toBeInstanceOf(Collection::class)
        ->and(Str::of('a,b')->explode(',')->all())->toBe(['a', 'b']);
});

test('predicates return plain booleans', function () {
    expect(Str::of('abc')->contains('b'))->toBeTrue()
        ->and(Str::of('abc')->startsWith('a'))->toBeTrue()
        ->and(Str::of('abc')->isEmpty())->toBeFalse()
        ->and(Str::of('')->isEmpty())->toBeTrue()
        ->and(Str::of('abc')->exactly('abc'))->toBeTrue();
});

test('a stringable is a native \Stringable', function () {
    expect(Str::of('a'))->toBeInstanceOf(\Stringable::class);
});

test('singular delegates through to the pluralizer', function () {
    expect(Str::of('users')->singular()->value())->toBe('user');
});

test('array access reads characters', function () {
    expect(Str::of('abc')[1])->toBe('b');
});
