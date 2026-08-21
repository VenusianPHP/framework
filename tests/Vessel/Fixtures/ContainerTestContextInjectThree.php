<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectThree
{
    public $impl;

    public function __construct(IContainerContextContractStub $impl)
    {
        $this->impl = $impl;
    }
}
