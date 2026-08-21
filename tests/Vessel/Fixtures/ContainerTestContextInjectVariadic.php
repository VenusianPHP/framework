<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectVariadic
{
    public $stubs;

    public function __construct(IContainerContextContractStub ...$stubs)
    {
        $this->stubs = $stubs;
    }
}
