<?php

namespace Tests\Vessel\Fixtures;

class ChildClass
{
    /**
     * @var array
     */
    public $objects;

    public function __construct(TestInterface ...$objects)
    {
        $this->objects = $objects;
    }
}
