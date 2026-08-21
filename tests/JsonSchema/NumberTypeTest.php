<?php

use Voyager\JsonSchema\JsonSchema;

test('it may set a min value as a float', function () {
    $type = JsonSchema::number()->title('Price')->min(5.5);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'title' => 'Price',
        'minimum' => 5.5,
    ]);
});

test('it may set a min value as an int', function () {
    $type = JsonSchema::number()->title('Price')->min(5);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'title' => 'Price',
        'minimum' => 5,
    ]);
});

test('it may set a max value as a float', function () {
    $type = JsonSchema::number()->description('Max price')->max(10.75);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'description' => 'Max price',
        'maximum' => 10.75,
    ]);
});

test('it may set a max value as an int', function () {
    $type = JsonSchema::number()->description('Max price')->max(10);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'description' => 'Max price',
        'maximum' => 10,
    ]);
});

test('it may set a default value', function () {
    $type = JsonSchema::number()->default(9.99);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'default' => 9.99,
    ]);
});

test('it may set multiple of as a float', function () {
    $type = JsonSchema::number()->multipleOf(0.5);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'multipleOf' => 0.5,
    ]);
});

test('it may set multiple of as an int', function () {
    $type = JsonSchema::number()->multipleOf(3);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'multipleOf' => 3,
    ]);
});

test('it may combine multiple of with min and max', function () {
    $type = JsonSchema::number()->min(0.0)->max(10.0)->multipleOf(0.25);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'minimum' => 0.0,
        'maximum' => 10.0,
        'multipleOf' => 0.25,
    ]);
});

test('it may set an enum', function () {
    $type = JsonSchema::number()->enum([1, 2.5, 3]);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'enum' => [1, 2.5, 3],
    ]);
});
