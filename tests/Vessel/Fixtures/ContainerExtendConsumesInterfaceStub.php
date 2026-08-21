<?php

namespace Tests\Vessel\Fixtures;

class ContainerExtendConsumesInterfaceStub
{
    public function __construct(
        public ContainerExtendInterfaceStub $stub,
    ) {
    }
}
