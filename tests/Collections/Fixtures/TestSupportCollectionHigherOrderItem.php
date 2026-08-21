<?php

namespace Tests\Collections\Fixtures;

class TestSupportCollectionHigherOrderItem
{
    public $name;

    public function __construct($name = 'taylor')
    {
        $this->name = $name;
    }

    public function uppercase()
    {
        return $this->name = strtoupper($this->name);
    }

    public function is($name)
    {
        return $this->name === $name;
    }
}
