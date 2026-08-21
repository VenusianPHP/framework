<?php

use Voyager\JsonSchema\JsonSchema;

test('it serializes as boolean with metadata', function () {
    $type = JsonSchema::boolean()->title('Enabled')->description('Feature flag');

    expect($type->toArray())->toEqual([
        'type' => 'boolean',
        'title' => 'Enabled',
        'description' => 'Feature flag',
    ]);
});

test('it may set a default of true', function () {
    $type = JsonSchema::boolean()->default(true);

    expect($type->toArray())->toEqual([
        'type' => 'boolean',
        'default' => true,
    ]);
});

test('it may set a default of false', function () {
    $type = JsonSchema::boolean()->default(false);

    expect($type->toArray())->toEqual([
        'type' => 'boolean',
        'default' => false,
    ]);
});

test('it may set an enum', function () {
    $type = JsonSchema::boolean()->enum([true, false]);

    expect($type->toArray())->toEqual([
        'type' => 'boolean',
        'enum' => [true, false],
    ]);
});
