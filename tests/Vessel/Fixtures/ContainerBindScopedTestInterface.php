<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;

#[Bind(ContainerScopedAttribute::class, environments: ['test'])]
#[Bind(ContainerScopedAttribute::class, environments: ['test2'])]
interface ContainerBindScopedTestInterface
{
}
