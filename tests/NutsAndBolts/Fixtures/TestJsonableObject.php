<?php

namespace Tests\NutsAndBolts\Fixtures;

use Voyager\Contracts\NutsAndBolts\Jsonable;

class TestJsonableObject implements Jsonable
{
    public function toJson(int $options = 0): string
    {
        return '{"foo":"bar"}';
    }
}
