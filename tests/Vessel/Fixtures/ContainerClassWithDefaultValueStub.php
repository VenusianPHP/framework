<?php

namespace Tests\Vessel\Fixtures;

class ContainerClassWithDefaultValueStub
{
    public function __construct(
        public ?ContainerConcreteStub $noDefault,
        public ?ContainerConcreteStub $default = null,
    ) {
    }
}
