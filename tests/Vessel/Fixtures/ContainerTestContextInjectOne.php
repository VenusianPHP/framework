<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectOne
{
    public $impl;

    public function __construct(IContainerContextContractStub $impl)
    {
        $this->impl = $impl;
    }
}
