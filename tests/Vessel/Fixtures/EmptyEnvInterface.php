<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;

#[Bind(BadConcrete::class, environments: [])]
interface EmptyEnvInterface
{
}
