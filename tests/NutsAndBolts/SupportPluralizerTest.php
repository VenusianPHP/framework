<?php

use Voyager\NutsAndBolts\DataObjects\Str;

test('singular reduces a plural word', function () {
    expect(Str::singular('children'))->toBe('child');
});

test('plural expands a singular word', function () {
    expect(Str::plural('child'))->toBe('children')
        ->and(Str::plural('cod'))->toBe('cod')
        ->and(Str::plural('The word'))->toBe('The words')
        ->and(Str::plural('Bouqueté'))->toBe('Bouquetés');
});

test('singular preserves the casing of the input', function () {
    expect(Str::singular('Children'))->toBe('Child')
        ->and(Str::singular('CHILDREN'))->toBe('CHILD')
        ->and(Str::singular('Tests'))->toBe('Test');
});

test('plural preserves the casing of the input', function () {
    expect(Str::plural('Child'))->toBe('Children')
        ->and(Str::plural('CHILD'))->toBe('CHILDREN')
        ->and(Str::plural('Test'))->toBe('Tests')
        ->and(Str::plural('cHiLd'))->toBe('children');
});

test('plural inflects on the last word of a studly string', function () {
    expect(Str::plural('VortexField'))->toBe('VortexFields')
        ->and(Str::plural('MatrixField'))->toBe('MatrixFields')
        ->and(Str::plural('IndexField'))->toBe('IndexFields')
        ->and(Str::plural('VertexField'))->toBe('VertexFields')
        // This is expected behavior, use "Str::pluralStudly" instead.
        ->and(Str::plural('RealHuman'))->toBe('RealHumen');
});

test('plural treats a negative count by its magnitude', function () {
    expect(Str::plural('test', 1))->toBe('test')
        ->and(Str::plural('test', 2))->toBe('tests')
        ->and(Str::plural('test', -1))->toBe('test')
        ->and(Str::plural('test', -2))->toBe('tests');
});

test('plural is not applied when the string ends in a non-alphanumeric character', function () {
    expect(Str::plural('Alien.'))->toBe('Alien.')
        ->and(Str::plural('Alien!'))->toBe('Alien!')
        ->and(Str::plural('Alien '))->toBe('Alien ')
        ->and(Str::plural('50%'))->toBe('50%');
});

test('plural is applied when the string ends in a numeric character', function () {
    expect(Str::plural('User1'))->toBe('User1s')
        ->and(Str::plural('User2'))->toBe('User2s')
        ->and(Str::plural('User3'))->toBe('User3s');
});

test('plural counts an array', function () {
    expect(Str::plural('user', []))->toBe('users')
        ->and(Str::plural('user', ['one']))->toBe('user')
        ->and(Str::plural('user', ['one', 'two']))->toBe('users');
});

test('plural counts a collection', function () {
    expect(Str::plural('user', collect()))->toBe('users')
        ->and(Str::plural('user', collect(['one'])))->toBe('user')
        ->and(Str::plural('user', collect(['one', 'two'])))->toBe('users');
});

test('pluralStudly inflects the whole studly word', function () {
    expect(Str::pluralStudly('RealHuman', 2))->toBe('RealHumans')
        ->and(Str::pluralStudly('Model', 2))->toBe('Models')
        ->and(Str::pluralStudly('VortexField', 2))->toBe('VortexFields')
        ->and(Str::pluralStudly('MultipleWordsInOneString', 2))->toBe('MultipleWordsInOneStrings');
});

test('pluralStudly honours the count, negative included', function () {
    expect(Str::pluralStudly('RealHuman', 1))->toBe('RealHuman')
        ->and(Str::pluralStudly('RealHuman', 2))->toBe('RealHumans')
        ->and(Str::pluralStudly('RealHuman', -1))->toBe('RealHuman')
        ->and(Str::pluralStudly('RealHuman', -2))->toBe('RealHumans');
});

test('pluralStudly counts an array', function () {
    expect(Str::pluralStudly('SomeUser', []))->toBe('SomeUsers')
        ->and(Str::pluralStudly('SomeUser', ['one']))->toBe('SomeUser')
        ->and(Str::pluralStudly('SomeUser', ['one', 'two']))->toBe('SomeUsers');
});

test('pluralStudly counts a collection', function () {
    expect(Str::pluralStudly('SomeUser', collect()))->toBe('SomeUsers')
        ->and(Str::pluralStudly('SomeUser', collect(['one'])))->toBe('SomeUser')
        ->and(Str::pluralStudly('SomeUser', collect(['one', 'two'])))->toBe('SomeUsers');
});
