<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectTwo
{
    public $impl;

    public function __construct(IContainerContextContractStub $impl)
    {
        $this->impl = $impl;
    }
}
