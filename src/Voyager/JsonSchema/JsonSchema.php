<?php

namespace Voyager\JsonSchema;

use Closure;
use Voyager\JsonSchema\Types\Type;

/**
 * @method static Types\ObjectType object(Closure|array<string, Types\Type> $properties = [])
 * @method static Types\IntegerType integer()
 * @method static Types\NumberType number()
 * @method static Types\StringType string()
 * @method static Types\BooleanType boolean()
 * @method static Types\ArrayType array()
 * @method static Types\UnionType union(array<int, string> $types)
 */
class JsonSchema
{
    /**
     * Build a type from a raw array of the Venusian-supported JSON Schema subset.
     *
     * @param  array<string, mixed>  $schema
     *
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $schema): Type
    {
        return Deserializer::deserialize($schema);
    }

    /**
     * Dynamically pass static methods to the schema instance.
     */
    public static function __callStatic(string $name, mixed $arguments): Type
    {
        return (new JsonSchemaTypeFactory)->$name(...$arguments);
    }
}
