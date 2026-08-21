<?php

namespace Tests\Vessel\Fixtures;

class VariadicParentClass
{
    /**
     * @var \Tests\Vessel\Fixtures\ChildClass
     */
    public $child;

    /**
     * @var int
     */
    public $i;

    public function __construct(ChildClass $child, int $i = 0)
    {
        $this->child = $child;
        $this->i = $i;
    }
}
