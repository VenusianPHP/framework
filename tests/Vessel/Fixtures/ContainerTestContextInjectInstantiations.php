<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextInjectInstantiations implements IContainerContextContractStub
{
    public static $instantiations;

    public function __construct()
    {
        static::$instantiations++;
    }
}
