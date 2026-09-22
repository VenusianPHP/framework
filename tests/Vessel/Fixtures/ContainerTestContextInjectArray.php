<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerTestContextInjectArray
{
    public $stubs;

    public function __construct(array $stubs)
    {
        $this->stubs = $stubs;
    }
}
