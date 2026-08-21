<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectVariadicAfterNonVariadic
{
    public $other;
    public $stubs;

    public function __construct(ContainerContextNonContractStub $other, IContainerContextContractStub ...$stubs)
    {
        $this->other = $other;
        $this->stubs = $stubs;
    }
}
