<?php

use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;
use Opis\Uri\Uri;
use Tests\JsonSchema\Fixtures\Enums\IntBackedEnum;
use Tests\JsonSchema\Fixtures\Enums\StringBackedEnum;
use Tests\JsonSchema\Fixtures\Enums\UnitEnum;
use Voyager\JsonSchema\JsonSchema;

/**
 * A validator that can resolve the remote meta-schemas Opis pulls in.
 */
function jsonSchemaValidator(): Validator
{
    $loader = new SchemaLoader;
    $resolver = new SchemaResolver;

    $loader->setResolver($resolver);
    $resolver->registerProtocol('https', fn (Uri $uri) => file_get_contents($uri->__toString()));

    return new Validator($loader);
}

test('it renders as an array', function () {
    $type = JsonSchema::object([
        'age' => JsonSchema::integer()->min(0)->required(),
    ])->title('User')->description('User payload')->default(['age' => 20]);

    expect($type->toArray())->toEqual([
        'type' => 'object',
        'title' => 'User',
        'description' => 'User payload',
        'default' => ['age' => 20],
        'properties' => [
            'age' => [
                'type' => 'integer',
                'minimum' => 0,
            ],
        ],
        'required' => ['age'],
    ]);
});

test('it renders as a string', function () {
    $type = JsonSchema::object([
        'age' => JsonSchema::integer()->min(0)->required(),
    ])->title('User');

    expect($type->toString())->toBe(<<<'JSON'
    {
        "title": "User",
        "properties": {
            "age": {
                "minimum": 0,
                "type": "integer"
            }
        },
        "type": "object",
        "required": [
            "age"
        ]
    }
    JSON);
});

test('it renders as a string when cast', function () {
    $type = JsonSchema::object([
        'age' => JsonSchema::integer()->min(0)->required(),
    ])->description('Payload');

    expect((string) $type)->toBe(<<<'JSON'
    {
        "description": "Payload",
        "properties": {
            "age": {
                "minimum": 0,
                "type": "integer"
            }
        },
        "type": "object",
        "required": [
            "age"
        ]
    }
    JSON);
});

test('an object schema may be built from a closure over the factory', function () {
    $schema = JsonSchema::object(fn (JsonSchema $schema): array => [
        'name' => $schema->string()->required(),
        'age' => $schema->integer()->min(0),
    ]);

    expect($schema)->toBeInstanceOf(JsonSchema::class);
});

describe('enum from a class', function () {
    test('it throws for a class that does not exist', function () {
        expect(fn () => JsonSchema::string()->enum('NonExistentEnumClass'))
            ->toThrow(fn (InvalidArgumentException $e) => expect($e->getMessage())->toBe('The provided class must be a BackedEnum.')
                ->and($e->getCode())->toBe(0));
    });

    test('it throws for a class that is not an enum', function () {
        expect(fn () => JsonSchema::string()->enum(stdClass::class))
            ->toThrow(fn (InvalidArgumentException $e) => expect($e->getMessage())->toBe('The provided class must be a BackedEnum.')
                ->and($e->getCode())->toBe(0));
    });

    test('it throws for a pure enum', function () {
        expect(fn () => JsonSchema::string()->enum(UnitEnum::class))
            ->toThrow(fn (InvalidArgumentException $e) => expect($e->getMessage())->toBe('The provided class must be a BackedEnum.')
                ->and($e->getCode())->toBe(0));
    });
});

test('it produces a JSON schema the data validates against', function (Stringable $schema, mixed $data) {
    $result = jsonSchemaValidator()->validate($data, (string) $schema);

    expect($result->isValid())->toBeTrue($result->error()?->message() ?? 'The JSON schema is valid.');
})->with(fn () => [
    // StringType
    'string accepts a string' => [JsonSchema::string(), 'hello'],
    'string min 2 accepts a two character string' => [JsonSchema::string()->min(2), 'hi'],
    'string max 5 accepts a five character string' => [JsonSchema::string()->max(5), 'hello'],
    'string pattern accepts a match' => [JsonSchema::string()->pattern('^foo.*$'), 'foobar'],
    'string default accepts the default value' => [JsonSchema::string()->default('x'), 'x'],
    'string enum accepts a listed value' => [JsonSchema::string()->enum(['draft', 'published']), 'draft'],
    // additional StringType cases
    'string min 0 accepts an empty string' => [JsonSchema::string()->min(0), ''], // empty allowed with min 0
    'string max 0 accepts an empty string' => [JsonSchema::string()->max(0), ''], // exactly zero length
    'string min 1 max 3 accepts the min boundary' => [JsonSchema::string()->min(1)->max(3), 'a'], // boundary at min
    'string complex pattern accepts a match' => [JsonSchema::string()->pattern('^[A-Z]{2}[0-9]{2}$'), 'AB12'], // complex pattern
    'string enum including an empty string accepts it' => [JsonSchema::string()->enum(['', 'x', 'y']), ''], // enum including empty string
    'string enum from a backed enum accepts a case value' => [JsonSchema::string()->enum(StringBackedEnum::class), 'one'], // string backed enum cases
    'nullable string accepts null' => [JsonSchema::string()->nullable(), null],
    'non nullable string accepts an empty string' => [JsonSchema::string()->nullable(false), ''],

    // IntegerType
    'integer accepts an integer' => [JsonSchema::integer(), 10],
    'integer min 0 accepts zero' => [JsonSchema::integer()->min(0), 0],
    'integer max 120 accepts the max boundary' => [JsonSchema::integer()->max(120), 120],
    'integer default accepts the default value' => [JsonSchema::integer()->default(18), 18],
    'integer enum accepts a listed value' => [JsonSchema::integer()->enum([1, 2, 3]), 2],
    'integer multiple of 5 accepts 10' => [JsonSchema::integer()->multipleOf(5), 10],
    'integer multiple of 3 accepts 9' => [JsonSchema::integer()->multipleOf(3), 9],
    'integer multiple of 7 accepts zero' => [JsonSchema::integer()->multipleOf(7), 0],
    // additional IntegerType cases
    'integer min -5 accepts the negative boundary' => [JsonSchema::integer()->min(-5), -5], // negative boundary
    'integer max 10 accepts a value below the max' => [JsonSchema::integer()->max(10), 9], // below max
    'integer min 1 max 3 accepts the max boundary' => [JsonSchema::integer()->min(1)->max(3), 3], // boundary at max
    'integer enum with zero accepts zero' => [JsonSchema::integer()->enum([0, -1, 5]), 0], // enum with zero
    'integer enum from a backed enum accepts a case value' => [JsonSchema::integer()->enum(IntBackedEnum::class), 1], // integer backed enum cases
    'integer default zero accepts zero' => [JsonSchema::integer()->default(0), 0], // default value
    'nullable integer accepts null' => [JsonSchema::integer()->nullable(), null],
    'non nullable integer accepts zero' => [JsonSchema::integer()->nullable(false), 0],

    // NumberType
    'number accepts a float' => [JsonSchema::number(), 3.14],
    'number min 0.0 accepts zero' => [JsonSchema::number()->min(0.0), 0.0],
    'number max 100.0 accepts a value below the max' => [JsonSchema::number()->max(100.0), 99.9],
    'number default accepts the default value' => [JsonSchema::number()->default(9.99), 9.99],
    'number enum accepts a listed value' => [JsonSchema::number()->enum([1, 2.5, 3]), 2.5],
    'number multiple of 0.5 accepts 2.5' => [JsonSchema::number()->multipleOf(0.5), 2.5],
    'number multiple of 3 accepts 9' => [JsonSchema::number()->multipleOf(3), 9],
    'number multiple of 0.1 accepts 0.3' => [JsonSchema::number()->multipleOf(0.1), 0.3],
    // additional NumberType cases
    'number min -10.5 accepts the negative boundary' => [JsonSchema::number()->min(-10.5), -10.5], // negative boundary
    'number min 0 max 1 accepts the max boundary' => [JsonSchema::number()->min(0)->max(1), 1.0], // boundary at max
    'number accepts an integer' => [JsonSchema::number(), 5], // integers are numbers
    'number enum accepts zero' => [JsonSchema::number()->enum([0.0, 1.1]), 0.0],
    'number default zero accepts zero' => [JsonSchema::number()->default(0.0), 0.0],
    'nullable number accepts null' => [JsonSchema::number()->nullable(), null],
    'non nullable number accepts zero' => [JsonSchema::number()->nullable(false), 0.0],

    // BooleanType
    'boolean accepts true' => [JsonSchema::boolean(), true],
    'boolean default false accepts false' => [JsonSchema::boolean()->default(false), false],
    'boolean enum accepts true' => [JsonSchema::boolean()->enum([true, false]), true],
    // additional BooleanType cases
    'boolean accepts false' => [JsonSchema::boolean(), false],
    'boolean enum accepts false' => [JsonSchema::boolean()->enum([true, false]), false],
    'boolean enum of only true accepts true' => [JsonSchema::boolean()->enum([true]), true],
    'boolean enum of only false accepts false' => [JsonSchema::boolean()->enum([false]), false],
    'boolean default true accepts true' => [JsonSchema::boolean()->default(true), true],
    'nullable boolean accepts null' => [JsonSchema::boolean()->nullable(), null],
    'non nullable boolean accepts false' => [JsonSchema::boolean()->nullable(false), false],

    // ObjectType
    'object accepts a payload matching its properties' => [
        JsonSchema::object([
            'name' => JsonSchema::string()->required(),
            'age' => JsonSchema::integer()->min(21),
        ]),
        (object) ['name' => 'Nuno', 'age' => 30],
    ],
    'object accepts an empty nested object without additional properties' => [
        JsonSchema::object([
            'meta' => JsonSchema::object()->withoutAdditionalProperties(),
        ]),
        (object) ['meta' => (object) []],
    ],
    'object accepts a nested object matching its default' => [
        JsonSchema::object([
            'config' => JsonSchema::object()->default(['x' => 1]),
        ]),
        (object) ['config' => (object) ['x' => 1]],
    ],
    'object accepts a listed enum value on a property' => [
        JsonSchema::object([
            'status' => JsonSchema::string()->enum(['draft', 'published']),
        ]),
        (object) ['status' => 'published'],
    ],
    // additional ObjectType cases
    'empty object accepts an empty object' => [
        JsonSchema::object([]),
        (object) [], // empty object allowed
    ],
    'object accepts only the required property' => [
        JsonSchema::object([
            'name' => JsonSchema::string()->required(),
        ]),
        (object) ['name' => 'John'], // only required present
    ],
    'object accepts an omitted optional property' => [
        JsonSchema::object([
            'meta' => JsonSchema::object()->withoutAdditionalProperties(),
        ]),
        (object) [], // optional property omitted
    ],
    'object accepts additional properties by default' => [
        JsonSchema::object([
            'name' => JsonSchema::string(),
        ]),
        (object) ['name' => 'Jane', 'extra' => 1], // additional properties allowed by default
    ],
    'object accepts the min boundary on a property' => [
        JsonSchema::object([
            'age' => JsonSchema::integer()->min(0),
        ]),
        (object) ['age' => 0], // boundary at min
    ],
    'object accepts null for a nullable property' => [
        JsonSchema::object([
            'age' => JsonSchema::integer()->nullable(),
        ]),
        (object) ['age' => null], // nullable
    ],
    'object accepts zero for a non nullable property' => [
        JsonSchema::object([
            'age' => JsonSchema::integer()->nullable(false),
        ]),
        (object) ['age' => 0], // not nullable
    ],

    // ArrayType
    'array accepts an empty array' => [JsonSchema::array(), []],
    'array min 1 accepts one item' => [JsonSchema::array()->min(1), ['a']],
    'array max 2 accepts two items' => [JsonSchema::array()->max(2), ['a', 'b']],
    'array items accepts matching items' => [JsonSchema::array()->items(JsonSchema::string()->max(3)), ['one', 'two']],
    'array default accepts the default value' => [JsonSchema::array()->default(['x']), ['x']],
    'array enum accepts a listed value' => [JsonSchema::array()->enum([['a'], ['b', 'c']]), ['b', 'c']],
    'unique array accepts distinct items' => [JsonSchema::array()->unique(), [1, 2, 3]],
    'unique array of strings accepts distinct items' => [JsonSchema::array()->items(JsonSchema::string())->unique(), ['a', 'b', 'c']],
    'unique array accepts an empty array' => [JsonSchema::array()->unique(), []],
    // additional ArrayType cases
    'array min 0 accepts an empty array' => [JsonSchema::array()->min(0), []], // explicit min zero
    'array max 0 accepts an empty array' => [JsonSchema::array()->max(0), []], // exactly zero length
    'array min 2 max 2 accepts exactly two items' => [JsonSchema::array()->items(JsonSchema::string())->min(2)->max(2), ['a', 'b']],
    'array of integers accepts matching items' => [JsonSchema::array()->items(JsonSchema::integer()->min(0)), [0, 1, 2]],
    'array enum of an empty array accepts an empty array' => [JsonSchema::array()->enum([[]]), []],
    'nullable array accepts null' => [JsonSchema::array()->nullable(), null],
    'non nullable array accepts an empty array' => [JsonSchema::array()->nullable(false), []],

    // UnionType
    'union of string and number accepts a string' => [JsonSchema::union(['string', 'number']), 'hello'],
    'union of string and number accepts an integer' => [JsonSchema::union(['string', 'number']), 42],
    'union of string and number accepts a float' => [JsonSchema::union(['string', 'number']), 3.14],
    'union of integer and boolean accepts a boolean' => [JsonSchema::union(['integer', 'boolean']), true],
    'union enum accepts a listed value' => [JsonSchema::union(['string', 'number'])->enum(['draft', 5]), 'draft'],
    'nullable union accepts null' => [JsonSchema::union(['string', 'number'])->nullable(), null],
    'nullable union accepts a member value' => [JsonSchema::union(['string', 'number'])->nullable(), 'still valid'],
]);

test('it produces a JSON schema the data does not validate against', function (Stringable $schema, mixed $data) {
    $result = jsonSchemaValidator()->validate($data, (string) $schema);

    expect($result->isValid())->toBeFalse();
})->with(fn () => [
    // StringType
    'string rejects an integer' => [JsonSchema::string(), 123], // type mismatch
    'string min 3 rejects a shorter string' => [JsonSchema::string()->min(3), 'hi'], // too short
    'string max 2 rejects a longer string' => [JsonSchema::string()->max(2), 'long'], // too long
    'string pattern rejects a non match' => [JsonSchema::string()->pattern('^foo.*$'), 'barbaz'], // pattern mismatch
    'string default does not excuse a wrong type' => [JsonSchema::string()->default('x'), 10], // default doesn't enforce, but data wrong type
    'string enum rejects an unlisted value' => [JsonSchema::string()->enum(['draft', 'published']), 'archived'], // not in enum
    // additional StringType cases
    'string min 1 rejects an empty string' => [JsonSchema::string()->min(1), ''], // too short (empty)
    'string max 0 rejects a one character string' => [JsonSchema::string()->max(0), 'a'], // too long for zero max
    'string pattern rejects a partial match' => [JsonSchema::string()->pattern('^[a]+$'), 'ab'], // pattern mismatch
    'string enum is case sensitive' => [JsonSchema::string()->enum(['a', 'b']), 'A'], // case sensitive mismatch
    'string enum from a backed enum rejects an unlisted value' => [JsonSchema::string()->enum(StringBackedEnum::class), 'three'], // string backed enum cases mismatch
    'string rejects null' => [JsonSchema::string(), null], // null not allowed
    'non nullable string rejects null' => [JsonSchema::string()->nullable(false), null], // not nullable

    // IntegerType
    'integer rejects a numeric string' => [JsonSchema::integer(), '10'], // type mismatch
    'integer min 5 rejects a smaller value' => [JsonSchema::integer()->min(5), 4], // below min
    'integer max 5 rejects a larger value' => [JsonSchema::integer()->max(5), 6], // above max
    'integer default does not excuse a wrong type' => [JsonSchema::integer()->default(1), '1'], // wrong type
    'integer enum rejects an unlisted value' => [JsonSchema::integer()->enum([1, 2, 3]), 4], // not in enum
    'integer multiple of 5 rejects 7' => [JsonSchema::integer()->multipleOf(5), 7], // not a multiple
    'integer multiple of 3 rejects 10' => [JsonSchema::integer()->multipleOf(3), 10], // not a multiple
    // additional IntegerType cases
    'integer min 0 rejects a negative value' => [JsonSchema::integer()->min(0), -1], // below min boundary
    'integer max 0 rejects a positive value' => [JsonSchema::integer()->max(0), 1], // above max boundary
    'integer rejects a float' => [JsonSchema::integer(), 3.14], // not an integer
    'integer enum rejects an unlisted float' => [JsonSchema::integer()->enum([1, 2]), 2.5], // not in enum and not an integer
    'integer enum from a backed enum rejects an unlisted value' => [JsonSchema::integer()->enum(IntBackedEnum::class), 3], // integer backed enum cases mismatch
    'integer default does not excuse null' => [JsonSchema::integer()->default(1), null], // wrong type
    'non nullable integer rejects null' => [JsonSchema::integer()->nullable(false), null], // not nullable

    // NumberType
    'number rejects a numeric string' => [JsonSchema::number(), '3.14'], // type mismatch
    'number min 0.5 rejects a smaller value' => [JsonSchema::number()->min(0.5), 0.4], // below min
    'number max 1.5 rejects a larger value' => [JsonSchema::number()->max(1.5), 1.6], // above max
    'number default does not excuse a wrong type' => [JsonSchema::number()->default(1.1), '1.1'], // wrong type
    'number enum rejects an unlisted value' => [JsonSchema::number()->enum([1, 2.5, 3]), 4], // not in enum
    'number multiple of 0.5 rejects 1.3' => [JsonSchema::number()->multipleOf(0.5), 1.3], // not a multiple
    'number multiple of 3 rejects 10' => [JsonSchema::number()->multipleOf(3), 10], // not a multiple
    // additional NumberType cases
    'number min 0 rejects a slightly negative value' => [JsonSchema::number()->min(0), -0.0001], // below min
    'number max 10 rejects a slightly larger value' => [JsonSchema::number()->max(10), 10.0001], // above max
    'number rejects a non numeric string' => [JsonSchema::number(), 'NaN'], // string, not number
    'number enum rejects a nearly equal value' => [JsonSchema::number()->enum([1.1, 2.2]), 1.1000001], // not exactly in enum
    'number default does not excuse an array' => [JsonSchema::number()->default(0.0), []], // wrong type
    'non nullable number rejects null' => [JsonSchema::number()->nullable(false), null], // not nullable

    // BooleanType
    'boolean rejects the string true' => [JsonSchema::boolean(), 'true'], // type mismatch
    'boolean default does not excuse zero' => [JsonSchema::boolean()->default(false), 0], // wrong type
    'boolean enum rejects null' => [JsonSchema::boolean()->enum([true, false]), null], // not in enum
    // additional BooleanType cases
    'boolean enum of only true rejects false' => [JsonSchema::boolean()->enum([true]), false], // not in enum
    'boolean enum of only false rejects true' => [JsonSchema::boolean()->enum([false]), true], // not in enum
    'boolean rejects one' => [JsonSchema::boolean(), 1], // wrong type
    'boolean default true does not excuse the string true' => [JsonSchema::boolean()->default(true), 'true'], // wrong type
    'boolean rejects null' => [JsonSchema::boolean(), null], // null is invalid
    'non nullable boolean rejects null' => [JsonSchema::boolean()->nullable(false), null], // not nullable

    // ObjectType
    'object rejects a payload missing a required property' => [JsonSchema::object(['name' => JsonSchema::string()->required()]), (object) []], // missing required
    'object rejects an additional property in a nested object' => [JsonSchema::object(['meta' => JsonSchema::object()->withoutAdditionalProperties()]), (object) ['meta' => (object) ['x' => 1]]], // additional prop not allowed
    'object rejects a value below the min of a nested schema' => [JsonSchema::object(['age' => JsonSchema::integer()->min(21)]), (object) ['age' => 18]], // below min in nested schema
    // additional ObjectType cases
    'empty object without additional properties rejects any property' => [JsonSchema::object([])->withoutAdditionalProperties(), (object) ['x' => 1]], // no additional properties allowed
    'object rejects a wrong type for a property' => [JsonSchema::object(['name' => JsonSchema::string()]), (object) ['name' => 123]], // wrong type for property
    'object rejects a wrong type for a nested object' => [JsonSchema::object(['meta' => JsonSchema::object()->withoutAdditionalProperties()]), (object) ['meta' => 'nope']], // wrong type in nested object
    'an object default does not satisfy a missing required property' => [JsonSchema::object(['name' => JsonSchema::string()->required()])->default(['name' => 'x']), (object) []], // default doesn't satisfy missing required
    'object rejects a value below the min on a property' => [JsonSchema::object(['score' => JsonSchema::integer()->min(0)]), (object) ['score' => -1]], // below min
    'object rejects null for a non nullable property' => [JsonSchema::object(['age' => JsonSchema::integer()->nullable(false)]), (object) ['age' => null]], // not nullable

    // ArrayType
    'array rejects an object' => [JsonSchema::array(), (object) []], // type mismatch
    'array min 2 rejects one item' => [JsonSchema::array()->min(2), ['a']], // too few items
    'array max 1 rejects two items' => [JsonSchema::array()->max(1), ['a', 'b']], // too many items
    'array items rejects an item that is too long' => [JsonSchema::array()->items(JsonSchema::string()->max(3)), ['four']], // item too long
    'array enum rejects an unlisted value' => [JsonSchema::array()->enum([['a'], ['b', 'c']]), ['c', 'd']], // not in enum
    'unique array rejects duplicate integers' => [JsonSchema::array()->unique(), [1, 1, 2]],
    'unique array of strings rejects duplicate items' => [JsonSchema::array()->items(JsonSchema::string())->unique(), ['a', 'b', 'a']],
    // additional ArrayType cases
    'array of integers rejects a string item' => [JsonSchema::array()->items(JsonSchema::integer()), ['a']], // wrong item type
    'array min 1 rejects an empty array' => [JsonSchema::array()->min(1), []], // too few
    'array max 0 rejects one item' => [JsonSchema::array()->max(0), ['a']], // too many for zero max
    'array enum rejects a value that is not equal to any member' => [JsonSchema::array()->enum([['a'], ['b']]), ['a', 'b']], // not equal to any enum member
    'array items max 1 rejects an item that is too long' => [JsonSchema::array()->items(JsonSchema::string()->max(1)), ['ab']], // item too long
    'non nullable array rejects null' => [JsonSchema::array()->nullable(false), null], // not nullable

    // UnionType
    'union of string and number rejects a boolean' => [JsonSchema::union(['string', 'number']), true], // boolean not in union
    'union of string and number rejects an array' => [JsonSchema::union(['string', 'number']), []], // array not in union
    'union of string and number rejects null' => [JsonSchema::union(['string', 'number']), null], // null not allowed unless nullable
    'union of integer and boolean rejects a string' => [JsonSchema::union(['integer', 'boolean']), 'nope'], // string not in union
    'union enum rejects an unlisted value' => [JsonSchema::union(['string', 'number'])->enum(['draft', 5]), 'archived'], // not in enum
]);
