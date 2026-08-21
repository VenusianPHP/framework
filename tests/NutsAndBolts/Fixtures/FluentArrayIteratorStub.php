<?php

namespace Tests\NutsAndBolts\Fixtures;

use ArrayIterator;
use IteratorAggregate;

class FluentArrayIteratorStub implements IteratorAggregate
{
    protected $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
}
