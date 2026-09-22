<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerExtendInterfaceImplementationStub implements ContainerExtendInterfaceStub
{
    public function __construct(
        public string $value,
    ) {
    }
}
