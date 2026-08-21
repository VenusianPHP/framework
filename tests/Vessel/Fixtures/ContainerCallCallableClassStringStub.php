<?php

namespace Tests\Vessel\Fixtures;

class ContainerCallCallableClassStringStub
{
    public $stub;

    public $default;

    public function __construct(ContainerCallConcreteStub $stub, $default = 'jeffrey')
    {
        $this->stub = $stub;
        $this->default = $default;
    }

    public function __invoke(ContainerTestCallStub $dependency)
    {
        return [$this->stub, $this->default, $dependency];
    }
}
