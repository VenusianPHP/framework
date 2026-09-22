<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerContextualBindingCallTarget
{
    public function __construct()
    {
    }

    public function work(IVesselContractStub $stub)
    {
        return $stub;
    }
}
