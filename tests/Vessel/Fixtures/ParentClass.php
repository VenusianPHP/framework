<?php

namespace Tests\Vessel\Fixtures;

class ParentClass
{
    /**
     * @var int
     */
    public $i;

    public function __construct(?TestInterface $testObject = null, int $i = 0)
    {
        $this->i = $i;
    }
}
