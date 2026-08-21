<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;

#[Bind(CliConcrete::class, environments: 'cli')]
interface CliOnlyInterface
{
}
