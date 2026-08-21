<?php

use Voyager\JsonSchema\JsonSchema;
use Voyager\JsonSchema\JsonSchemaTypeFactory;

test('it may not have properties', function () {
    $type = JsonSchema::object()->title('Payload');

    expect($type->toArray())->toEqual([
        'type' => 'object',
        'title' => 'Payload',
    ]);
});

test('it may be initialized with a closure but without properties', function () {
    $type = JsonSchema::object(fn () => [])->title('Payload');

    expect($type->toArray())->toEqual([
        'type' => 'object',
        'title' => 'Payload',
    ]);
});

test('it may have properties', function () {
    $type = JsonSchema::object([
        'age-a' => JsonSchema::integer()->min(0)->required(),
        'age-b' => JsonSchema::integer()->default(30)->max(45),
    ])->description('Root object');

    expect($type->toArray())->toEqual([
        'type' => 'object',
        'description' => 'Root object',
        'properties' => [
            'age-a' => [
                'type' => 'integer',
                'minimum' => 0,
            ],
            'age-b' => [
                'type' => 'integer',
                'default' => 30,
                'maximum' => 45,
            ],
        ],
        'required' => ['age-a'],
    ]);
});

test('it may be initialized with a closure and still have properties', function () {
    $type = JsonSchema::object(fn (JsonSchemaTypeFactory $schema) => [
        'age-a' => $schema->integer()->min(0)->required(),
        'age-b' => $schema->integer()->default(30)->max(45),
    ])->description('Root object');

    expect($type->toArray())->toEqual([
        'type' => 'object',
        'description' => 'Root object',
        'properties' => [
            'age-a' => [
                'type' => 'integer',
                'minimum' => 0,
            ],
            'age-b' => [
                'type' => 'integer',
                'default' => 30,
                'maximum' => 45,
            ],
        ],
        'required' => ['age-a'],
    ]);
});

test('numeric string property names remain strings in the required array', function () {
    $type = JsonSchema::object([
        '1' => JsonSchema::string()->required(),
        '4' => JsonSchema::string()->required(),
    ]);

    $array = $type->toArray();

    expect($array['required'])->toBe(['1', '4'])
        ->and($array['required'][0])->toBeString()
        ->and($array['required'][1])->toBeString();
});

test('it may disable additional properties', function () {
    $type = JsonSchema::object()->default(['age' => 1])->withoutAdditionalProperties();

    expect($type->toArray())->toEqual([
        'type' => 'object',
        'default' => ['age' => 1],
        'additionalProperties' => false,
    ]);
});

test('it may set an enum', function () {
    $type = JsonSchema::object()->enum([
        ['a' => 1],
        ['a' => 2],
    ]);

    expect($type->toArray())->toEqual([
        'type' => 'object',
        'enum' => [
            ['a' => 1],
            ['a' => 2],
        ],
    ]);
});
