<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerCallCallableStub
{
    public function __invoke(ContainerCallConcreteStub $stub, $default = 'jeffrey')
    {
        return func_get_args();
    }
}
