<?php

use Voyager\JsonSchema\JsonSchema;
use Voyager\JsonSchema\Serializer;
use Voyager\JsonSchema\Types\UnionType;

test('it serializes as a type array', function () {
    $type = JsonSchema::union(['string', 'number', 'boolean']);

    expect($type->toArray())->toEqual([
        'type' => ['string', 'number', 'boolean'],
    ]);
});

test('it serializes with metadata', function () {
    $type = JsonSchema::union(['string', 'number'])
        ->title('Value')
        ->description('A string or a number');

    expect($type->toArray())->toEqual([
        'type' => ['string', 'number'],
        'title' => 'Value',
        'description' => 'A string or a number',
    ]);
});

test('it dedupes members and preserves their order', function () {
    $type = JsonSchema::union(['number', 'string', 'number', 'boolean', 'string']);

    expect($type->types())->toBe(['number', 'string', 'boolean'])
        ->and($type->toArray())->toBe(['type' => ['number', 'string', 'boolean']]);
});

test('it appends null when nullable', function () {
    $type = JsonSchema::union(['string', 'number'])->nullable();

    expect($type->toArray())->toEqual([
        'type' => ['string', 'number', 'null'],
    ]);
});

test('it normalizes a null member into nullability', function () {
    $type = JsonSchema::union(['string', 'number', 'null']);

    expect($type->types())->toBe(['string', 'number'])
        ->and($type->toArray())->toEqual([
            'type' => ['string', 'number', 'null'],
        ]);
});

test('it does not duplicate null when already nullable', function () {
    $type = JsonSchema::union(['string', 'null'])->nullable();

    expect($type->toArray())->toEqual([
        'type' => ['string', 'null'],
    ]);
});

test('it rejects an unsupported member name', function () {
    JsonSchema::union(['string', 'wat']);
})->throws(InvalidArgumentException::class, 'Unsupported JSON Schema type [wat] in a multi-type union.');

test('it rejects a non string member', function () {
    JsonSchema::union(['string', 123]);
})->throws(InvalidArgumentException::class, 'Unsupported JSON Schema type [123] in a multi-type union.');

test('it round trips a union', function () {
    $schema = ['type' => ['string', 'number', 'boolean']];

    $type = JsonSchema::fromArray($schema);

    expect($type)->toBeInstanceOf(UnionType::class)
        ->and(Serializer::serialize($type))->toBe($schema)
        ->and(JsonSchema::fromArray(Serializer::serialize($type)))->toEqual($type);
});

test('it round trips a nullable union', function () {
    $schema = ['type' => ['string', 'number', 'null']];

    $type = JsonSchema::fromArray($schema);

    expect($type)->toBeInstanceOf(UnionType::class)
        ->and(Serializer::serialize($type))->toBe($schema);
});
