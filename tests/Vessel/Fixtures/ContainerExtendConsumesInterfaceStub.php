<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerExtendConsumesInterfaceStub
{
    public function __construct(
        public ContainerExtendInterfaceStub $stub,
    ) {
    }
}
