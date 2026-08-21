<?php

namespace Tests\Vessel\Fixtures;

class ContainerTestContextWithOptionalInnerDependency
{
    public $inner;

    public function __construct(?ContainerTestContextInjectOne $inner = null)
    {
        $this->inner = $inner;
    }
}
