<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerTestContextInjectOne
{
    public $impl;

    public function __construct(IContainerContextContractStub $impl)
    {
        $this->impl = $impl;
    }
}
