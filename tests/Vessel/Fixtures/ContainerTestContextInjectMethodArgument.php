<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectMethodArgument
{
    public function method(IContainerContextContractStub $dependency)
    {
        return $dependency;
    }
}
