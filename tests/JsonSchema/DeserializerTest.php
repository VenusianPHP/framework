<?php

use Voyager\JsonSchema\JsonSchema;
use Voyager\JsonSchema\Serializer;
use Voyager\JsonSchema\Types\ArrayType;
use Voyager\JsonSchema\Types\BooleanType;
use Voyager\JsonSchema\Types\IntegerType;
use Voyager\JsonSchema\Types\NumberType;
use Voyager\JsonSchema\Types\ObjectType;
use Voyager\JsonSchema\Types\StringType;
use Voyager\JsonSchema\Types\UnionType;

test('it round trips a type built with the factory', function () {
    $type = JsonSchema::object([
        'name' => JsonSchema::string()->min(1)->max(50)->pattern('^[a-z]+$')->required(),
        'age' => JsonSchema::integer()->min(0)->max(120)->default(18),
        'score' => JsonSchema::number()->min(0)->max(100)->multipleOf(0.5),
        'active' => JsonSchema::boolean()->default(true),
        'tags' => JsonSchema::array()->items(JsonSchema::string()->max(20))->min(1)->max(5)->unique(),
        'meta' => JsonSchema::object([
            'created' => JsonSchema::string()->format('date-time')->required(),
        ])->withoutAdditionalProperties(),
        'status' => JsonSchema::string()->enum(['draft', 'published'])->nullable(),
    ])->title('User')->description('A user payload');

    $array = Serializer::serialize($type);

    $rebuilt = JsonSchema::fromArray($array);

    expect($rebuilt)->toBeInstanceOf(ObjectType::class)
        ->and(Serializer::serialize($rebuilt))->toBe($array)
        ->and($rebuilt)->toEqual($type);
});

test('it maps every supported type', function () {
    expect(JsonSchema::fromArray(['type' => 'object']))->toBeInstanceOf(ObjectType::class)
        ->and(JsonSchema::fromArray(['type' => 'array']))->toBeInstanceOf(ArrayType::class)
        ->and(JsonSchema::fromArray(['type' => 'string']))->toBeInstanceOf(StringType::class)
        ->and(JsonSchema::fromArray(['type' => 'integer']))->toBeInstanceOf(IntegerType::class)
        ->and(JsonSchema::fromArray(['type' => 'number']))->toBeInstanceOf(NumberType::class)
        ->and(JsonSchema::fromArray(['type' => 'boolean']))->toBeInstanceOf(BooleanType::class);
});

describe('constraints', function () {
    test('it applies string constraints', function () {
        $type = JsonSchema::fromArray([
            'type' => 'string',
            'minLength' => 2,
            'maxLength' => 8,
            'pattern' => '^foo.*$',
            'format' => 'email',
        ]);

        expect($type->toArray())->toEqual([
            'type' => 'string',
            'minLength' => 2,
            'maxLength' => 8,
            'pattern' => '^foo.*$',
            'format' => 'email',
        ]);
    });

    test('it applies integer constraints', function () {
        $type = JsonSchema::fromArray([
            'type' => 'integer',
            'minimum' => 0,
            'maximum' => 100,
            'multipleOf' => 5,
        ]);

        expect($type)->toBeInstanceOf(IntegerType::class)
            ->and($type->toArray())->toEqual([
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 100,
                'multipleOf' => 5,
            ]);
    });

    test('it applies number constraints and preserves floats', function () {
        $type = JsonSchema::fromArray([
            'type' => 'number',
            'minimum' => 0.5,
            'maximum' => 9.9,
            'multipleOf' => 0.1,
        ]);

        expect($type)->toBeInstanceOf(NumberType::class);

        $array = $type->toArray();

        expect($array['minimum'])->toBe(0.5)
            ->and($array['maximum'])->toBe(9.9)
            ->and($array['multipleOf'])->toBe(0.1);
    });

    test('it applies array constraints and nested items', function () {
        $type = JsonSchema::fromArray([
            'type' => 'array',
            'items' => ['type' => 'string', 'maxLength' => 3],
            'minItems' => 1,
            'maxItems' => 4,
            'uniqueItems' => true,
        ]);

        expect($type)->toBeInstanceOf(ArrayType::class)
            ->and($type->toArray())->toEqual([
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 4,
                'items' => [
                    'type' => 'string',
                    'maxLength' => 3,
                ],
                'uniqueItems' => true,
            ]);
    });

    test('it applies enum and default', function () {
        $type = JsonSchema::fromArray([
            'type' => 'string',
            'enum' => ['draft', 'published'],
            'default' => 'draft',
        ]);

        expect($type->toArray())->toEqual([
            'type' => 'string',
            'default' => 'draft',
            'enum' => ['draft', 'published'],
        ]);
    });

    test('it ignores unknown keywords', function () {
        $type = JsonSchema::fromArray([
            'type' => 'string',
            'minLength' => 1,
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$comment' => 'ignore me',
            'readOnly' => true,
            'contentEncoding' => 'base64',
        ]);

        expect($type->toArray())->toEqual([
            'type' => 'string',
            'minLength' => 1,
        ]);
    });
});

describe('objects', function () {
    test('it builds nested objects and marks required children', function () {
        $type = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1],
                'age' => ['type' => 'integer', 'minimum' => 0],
                'address' => [
                    'type' => 'object',
                    'properties' => [
                        'city' => ['type' => 'string'],
                    ],
                    'required' => ['city'],
                ],
            ],
            'required' => ['name'],
        ]);

        expect($type->toArray())->toEqual([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1],
                'age' => ['type' => 'integer', 'minimum' => 0],
                'address' => [
                    'type' => 'object',
                    'properties' => [
                        'city' => ['type' => 'string'],
                    ],
                    'required' => ['city'],
                ],
            ],
            'required' => ['name'],
        ]);
    });

    test('it preserves numeric string property names when marking required', function () {
        $type = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                '1' => ['type' => 'string'],
                '4' => ['type' => 'string'],
            ],
            'required' => ['1', '4'],
        ]);

        $array = $type->toArray();

        expect($array['required'])->toEqual(['1', '4'])
            ->and($array['required'][0])->toBeString();
    });

    test('it disallows additional properties when false', function () {
        $type = JsonSchema::fromArray([
            'type' => 'object',
            'additionalProperties' => false,
        ]);

        expect($type->toArray())->toEqual([
            'type' => 'object',
            'additionalProperties' => false,
        ]);
    });
});

describe('nullability', function () {
    test('it normalizes nullable from a type array', function () {
        $type = JsonSchema::fromArray([
            'type' => ['string', 'null'],
            'minLength' => 1,
        ]);

        expect($type)->toBeInstanceOf(StringType::class)
            ->and($type->toArray())->toEqual([
                'type' => ['string', 'null'],
                'minLength' => 1,
            ]);
    });

    test('it normalizes nullable from an anyOf null branch', function () {
        $type = JsonSchema::fromArray([
            'title' => 'Nickname',
            'anyOf' => [
                ['type' => 'string', 'minLength' => 1],
                ['type' => 'null'],
            ],
        ]);

        expect($type)->toBeInstanceOf(StringType::class)
            ->and($type->toArray())->toEqual([
                'title' => 'Nickname',
                'minLength' => 1,
                'type' => ['string', 'null'],
            ]);
    });

    test('it normalizes nullable from a oneOf null branch', function () {
        $type = JsonSchema::fromArray([
            'oneOf' => [
                ['type' => 'null'],
                ['type' => 'integer', 'minimum' => 0],
            ],
        ]);

        expect($type)->toBeInstanceOf(IntegerType::class)
            ->and($type->toArray())->toEqual([
                'minimum' => 0,
                'type' => ['integer', 'null'],
            ]);
    });
});

describe('$ref resolution', function () {
    test('it resolves a local ref against $defs', function () {
        $type = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'author' => ['$ref' => '#/$defs/User'],
            ],
            'required' => ['author'],
            '$defs' => [
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                ],
            ],
        ]);

        expect($type->toArray())->toEqual([
            'type' => 'object',
            'properties' => [
                'author' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                ],
            ],
            'required' => ['author'],
        ]);
    });

    test('it resolves a local ref against definitions', function () {
        $type = JsonSchema::fromArray([
            '$ref' => '#/definitions/Tag',
            'definitions' => [
                'Tag' => ['type' => 'string', 'minLength' => 1],
            ],
        ]);

        expect($type)->toBeInstanceOf(StringType::class)
            ->and($type->toArray())->toEqual([
                'type' => 'string',
                'minLength' => 1,
            ]);
    });

    test('it merges sibling keys over a ref', function () {
        $type = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'handle' => [
                    '$ref' => '#/$defs/Name',
                    'description' => 'Overridden description',
                ],
            ],
            '$defs' => [
                'Name' => [
                    'type' => 'string',
                    'description' => 'Original description',
                    'minLength' => 1,
                ],
            ],
        ]);

        expect($type->toArray())->toEqual([
            'type' => 'object',
            'properties' => [
                'handle' => [
                    'description' => 'Overridden description',
                    'minLength' => 1,
                    'type' => 'string',
                ],
            ],
        ]);
    });

    test('it resolves the same ref used in sibling positions', function () {
        $type = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'home' => ['$ref' => '#/$defs/address'],
                'work' => ['$ref' => '#/$defs/address'],
            ],
            '$defs' => [
                'address' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
            ],
        ]);

        expect($type->toArray())->toEqual([
            'type' => 'object',
            'properties' => [
                'home' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
                'work' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
            ],
        ]);
    });

    test('it throws for an unresolvable ref', function () {
        JsonSchema::fromArray([
            '$ref' => '#/$defs/Missing',
            '$defs' => [],
        ]);
    })->throws(InvalidArgumentException::class, 'Unable to resolve JSON Schema $ref [#/$defs/Missing].');

    test('it throws for a remote ref', function () {
        JsonSchema::fromArray([
            '$ref' => 'https://example.com/user.json',
        ]);
    })->throws(InvalidArgumentException::class, 'Unable to resolve non-local JSON Schema $ref [https://example.com/user.json].');

    test('it detects a circular ref instead of recursing', function () {
        JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'children' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/node']],
            ],
            '$defs' => [
                'node' => [
                    'type' => 'object',
                    'properties' => [
                        'children' => ['type' => 'array', 'items' => ['$ref' => '#/$defs/node']],
                    ],
                ],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'Circular JSON Schema $ref [#/$defs/node] detected.');

    test('it resolves the root ref pointer, which self-references and so is circular', function () {
        // "#" resolves to the root, so a self-reference is detected as circular...
        JsonSchema::fromArray(['$ref' => '#']);
    })->throws(InvalidArgumentException::class, 'Circular JSON Schema $ref [#] detected.');
});

describe('type inference', function () {
    test('it infers the object type from properties', function () {
        $type = JsonSchema::fromArray([
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ]);

        expect($type)->toBeInstanceOf(ObjectType::class);
    });

    test('it infers the array type from items', function () {
        $type = JsonSchema::fromArray([
            'items' => ['type' => 'integer'],
        ]);

        expect($type)->toBeInstanceOf(ArrayType::class)
            ->and($type->toArray())->toEqual([
                'type' => 'array',
                'items' => ['type' => 'integer'],
            ]);
    });

    test('it infers a scalar type from a homogeneous enum', function () {
        expect(JsonSchema::fromArray(['enum' => ['draft', 'published']]))->toBeInstanceOf(StringType::class)
            ->and(JsonSchema::fromArray(['enum' => [1, 2, 3]]))->toBeInstanceOf(IntegerType::class)
            ->and(JsonSchema::fromArray(['enum' => [1, 2.5, 3]]))->toBeInstanceOf(NumberType::class)
            ->and(JsonSchema::fromArray(['enum' => [true, false]]))->toBeInstanceOf(BooleanType::class);
    });

    test('it throws when the type cannot be determined', function () {
        JsonSchema::fromArray([
            'title' => 'Mystery',
        ]);
    })->throws(InvalidArgumentException::class, 'Unable to determine the JSON Schema type for the given schema.');
});

describe('unions', function () {
    test('it deserializes a multi type union', function () {
        $type = JsonSchema::fromArray([
            'type' => ['string', 'number', 'boolean'],
        ]);

        expect($type)->toBeInstanceOf(UnionType::class)
            ->and($type->types())->toBe(['string', 'number', 'boolean'])
            ->and($type->toArray())->toBe(['type' => ['string', 'number', 'boolean']]);
    });

    test('it deserializes a nullable multi type union', function () {
        $type = JsonSchema::fromArray([
            'type' => ['string', 'number', 'null'],
        ]);

        expect($type)->toBeInstanceOf(UnionType::class)
            ->and($type->types())->toBe(['string', 'number'])
            ->and($type->toArray())->toBe(['type' => ['string', 'number', 'null']]);
    });

    test('it does not treat a single type plus null as a union', function () {
        $type = JsonSchema::fromArray([
            'type' => ['string', 'null'],
        ]);

        expect($type)->toBeInstanceOf(StringType::class)
            ->and($type->toArray())->toBe(['type' => ['string', 'null']]);
    });

    test('it dedupes and preserves the order of union members', function () {
        $type = JsonSchema::fromArray([
            'type' => ['number', 'string', 'number', 'boolean', 'string'],
        ]);

        expect($type)->toBeInstanceOf(UnionType::class)
            ->and($type->types())->toBe(['number', 'string', 'boolean']);
    });

    test('it deserializes a union nested in an object property', function () {
        $type = JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'value' => ['type' => ['string', 'number']],
            ],
        ]);

        expect($type)->toBeInstanceOf(ObjectType::class)
            ->and($type->toArray())->toEqual([
                'type' => 'object',
                'properties' => [
                    'value' => ['type' => ['string', 'number']],
                ],
            ]);
    });

    test('it deserializes a union nested in array items', function () {
        $type = JsonSchema::fromArray([
            'type' => 'array',
            'items' => ['type' => ['string', 'integer', 'null']],
        ]);

        expect($type)->toBeInstanceOf(ArrayType::class)
            ->and($type->toArray())->toEqual([
                'type' => 'array',
                'items' => ['type' => ['string', 'integer', 'null']],
            ]);
    });

    test('it throws for an unsupported union member', function () {
        JsonSchema::fromArray([
            'type' => ['string', 'wat'],
        ]);
    })->throws(InvalidArgumentException::class, 'Unsupported JSON Schema type [wat] in a multi-type union.');

    test('it throws for a non string union member', function () {
        JsonSchema::fromArray([
            'type' => ['string', 123],
        ]);
    })->throws(InvalidArgumentException::class, 'Unsupported JSON Schema type [123] in a multi-type union.');

    test('it throws when a union carries type specific keywords', function () {
        JsonSchema::fromArray([
            'type' => ['array', 'string'],
            'items' => ['type' => 'integer'],
        ]);
    })->throws(InvalidArgumentException::class, 'Type-specific keywords [items] are not supported on a multi-type JSON Schema union.');

    test('it throws when a union branch conflicts with sibling keys', function () {
        JsonSchema::fromArray([
            'type' => 'integer',
            'anyOf' => [
                ['type' => 'string', 'minLength' => 3],
                ['type' => 'null'],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'Conflicting [type] between a "anyOf" branch and its sibling keys.');

    test('it throws for an unsupported union', function () {
        JsonSchema::fromArray([
            'anyOf' => [
                ['type' => 'string'],
                ['type' => 'integer'],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'Only a nullable "anyOf" (a single schema plus a "null" branch) is supported.');
});

describe('rejected schemas', function () {
    test('it throws for a boolean property schema', function () {
        JsonSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'meta' => true,
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'Unable to represent the schema for property [meta]; boolean schemas are not supported.');

    test('it throws for a non numeric numeric constraint', function () {
        JsonSchema::fromArray([
            'type' => 'number',
            'minimum' => 'oops',
        ]);
    })->throws(InvalidArgumentException::class, 'The JSON Schema [minimum] constraint must be a number.');

    test('it throws for a non integer integer constraint', function () {
        JsonSchema::fromArray([
            'type' => 'integer',
            'minimum' => 1.9,
        ]);
    })->throws(InvalidArgumentException::class, 'The JSON Schema integer constraint [1.9] must be an integer.');

    test('it throws for tuple items', function () {
        JsonSchema::fromArray([
            'type' => 'array',
            'items' => [
                ['type' => 'string'],
                ['type' => 'integer'],
            ],
        ]);
    })->throws(InvalidArgumentException::class, 'Tuple and boolean JSON Schema "items" are not supported.');

    test('it throws for a null default', function () {
        JsonSchema::fromArray([
            'type' => 'string',
            'default' => null,
        ]);
    })->throws(InvalidArgumentException::class, 'A null JSON Schema [default] is not supported.');
});
