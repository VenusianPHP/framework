<?php

namespace Tests\NutsAndBolts\Fixtures;

use JsonSerializable;

class TestJsonSerializeObject implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        return ['foo' => 'bar'];
    }
}
