<?php

namespace Tests\Vessel\Fixtures;

final class ComplexDependency implements ContainerTestContract
{
    public function __construct(public bool $param)
    {
    }
}
