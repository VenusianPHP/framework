<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerDefaultValueStub
{
    public $stub;
    public $default;

    public function __construct(ContainerConcreteStub $stub, $default = 'taylor')
    {
        $this->stub = $stub;
        $this->default = $default;
    }
}
