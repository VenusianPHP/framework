<?php

namespace Tests\NutsAndBolts\Fixtures;

use JsonSerializable;

class TestJsonSerializeWithScalarValueObject implements JsonSerializable
{
    public function jsonSerialize(): string
    {
        return 'foo';
    }
}
