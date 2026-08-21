<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;

#[Bind(ContainerSingletonAttribute::class, environments: ['foo', ContainerTestEnvironments::Bar])]
interface ContainerBindSingletonTestInterface
{
}
