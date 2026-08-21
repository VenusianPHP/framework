<?php

namespace Tests\Collections\Fixtures;

use JsonSerializable;

class TestJsonSerializeToStringObject implements JsonSerializable
{
    public function jsonSerialize(): string
    {
        return 'foobar';
    }
}
