<?php

namespace Tests\Validation\fixtures;

use Voyager\Contracts\NutsAndBolts\Arrayable;

class Values implements Arrayable
{
    public function toArray()
    {
        return [1, 2, 3, 4];
    }
}
