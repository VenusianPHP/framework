<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;

#[Bind(ProdConcrete::class, environments: 'prod')]
#[Bind(DevConcrete::class, environments: 'dev')]
interface MultiEnvInterface
{
}
