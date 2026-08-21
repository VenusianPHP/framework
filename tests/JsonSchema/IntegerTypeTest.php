<?php

use Voyager\JsonSchema\JsonSchema;

test('it may set a min value', function () {
    $type = JsonSchema::integer()->title('Age')->min(5);

    expect($type->toArray())->toEqual([
        'type' => 'integer',
        'title' => 'Age',
        'minimum' => 5,
    ]);
});

test('it may set a max value', function () {
    $type = JsonSchema::integer()->description('Max age')->max(10);

    expect($type->toArray())->toEqual([
        'type' => 'integer',
        'description' => 'Max age',
        'maximum' => 10,
    ]);
});

test('it may set a default value', function () {
    $type = JsonSchema::integer()->default(18);

    expect($type->toArray())->toEqual([
        'type' => 'integer',
        'default' => 18,
    ]);
});

test('it may set multiple of', function () {
    $type = JsonSchema::integer()->multipleOf(5);

    expect($type->toArray())->toEqual([
        'type' => 'integer',
        'multipleOf' => 5,
    ]);
});

test('it may combine multiple of with min and max', function () {
    $type = JsonSchema::integer()->min(0)->max(100)->multipleOf(10);

    expect($type->toArray())->toEqual([
        'type' => 'integer',
        'minimum' => 0,
        'maximum' => 100,
        'multipleOf' => 10,
    ]);
});

test('it may set an enum', function () {
    $type = JsonSchema::integer()->enum([1, 2, 3]);

    expect($type->toArray())->toEqual([
        'type' => 'integer',
        'enum' => [1, 2, 3],
    ]);
});
