<?php

namespace Tests\NutsAndBolts\Fixtures;

use Voyager\Contracts\NutsAndBolts\Arrayable;

class TestArrayableObject implements Arrayable
{
    public function toArray()
    {
        return ['foo' => 'bar'];
    }
}
