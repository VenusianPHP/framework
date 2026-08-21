<?php

namespace Tests\Vessel\Fixtures;

class ContainerInjectVariableStubWithInterfaceImplementation implements IVesselContractStub
{
    public $something;

    public function __construct(ContainerConcreteStub $concrete, $something)
    {
        $this->something = $something;
    }
}
