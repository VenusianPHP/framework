<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectTwoInstances
{
    public $implOne;
    public $implTwo;

    public function __construct(ContainerTestContextWithOptionalInnerDependency $implOne, ContainerTestContextInjectTwo $implTwo)
    {
        $this->implOne = $implOne;
        $this->implTwo = $implTwo;
    }
}
