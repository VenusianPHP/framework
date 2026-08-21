<?php

use Voyager\JsonSchema\JsonSchema;

test('it may set min items', function () {
    $type = JsonSchema::array()->title('Tags')->min(1);

    expect($type->toArray())->toEqual([
        'type' => 'array',
        'title' => 'Tags',
        'minItems' => 1,
    ]);
});

test('it may set max items', function () {
    $type = JsonSchema::array()->description('A list of tags')->max(10);

    expect($type->toArray())->toEqual([
        'type' => 'array',
        'description' => 'A list of tags',
        'maxItems' => 10,
    ]);
});

test('it may set the items type', function () {
    $type = JsonSchema::array()->items(
        JsonSchema::string()->max(20)
    );

    expect($type->toArray())->toEqual([
        'type' => 'array',
        'items' => [
            'type' => 'string',
            'maxLength' => 20,
        ],
    ]);
});

test('it may set a default value', function () {
    $type = JsonSchema::array()->default(['a', 'b']);

    expect($type->toArray())->toEqual([
        'type' => 'array',
        'default' => ['a', 'b'],
    ]);
});

test('it may set unique items', function () {
    $type = JsonSchema::array()->items(JsonSchema::string())->unique();

    expect($type->toArray())->toEqual([
        'type' => 'array',
        'items' => [
            'type' => 'string',
        ],
        'uniqueItems' => true,
    ]);
});

test('it may combine unique items with min and max', function () {
    $type = JsonSchema::array()->min(1)->max(5)->unique();

    expect($type->toArray())->toEqual([
        'type' => 'array',
        'minItems' => 1,
        'maxItems' => 5,
        'uniqueItems' => true,
    ]);
});

test('it may set an enum', function () {
    $type = JsonSchema::array()->enum([
        ['a'],
        ['b', 'c'],
    ]);

    expect($type->toArray())->toEqual([
        'type' => 'array',
        'enum' => [
            ['a'],
            ['b', 'c'],
        ],
    ]);
});
