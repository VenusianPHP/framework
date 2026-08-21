<?php

use Voyager\JsonSchema\Types\Type;

test('it does not know how to serialize unknown types', function () {
    $type = new class extends Type
    {
        // anonymous type for triggering serializer failure
    };

    $type->toArray();
})->throws(RuntimeException::class, 'Unsupported [Voyager\JsonSchema\Types\Type@anonymous');
