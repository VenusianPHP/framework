<?php

namespace Tests\Vessel\Fixtures;

class ContainerDependentStub
{
    public $impl;

    public function __construct(IVesselContractStub $impl)
    {
        $this->impl = $impl;
    }
}
