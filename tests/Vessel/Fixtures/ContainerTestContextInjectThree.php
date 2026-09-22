<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerTestContextInjectThree
{
    public $impl;

    public function __construct(IContainerContextContractStub $impl)
    {
        $this->impl = $impl;
    }
}
