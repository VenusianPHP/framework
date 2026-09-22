<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerTestContextInjectInstantiations implements IContainerContextContractStub
{
    public static $instantiations;

    public function __construct()
    {
        static::$instantiations++;
    }
}
