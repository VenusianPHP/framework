<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;
use Voyager\Vessel\Attributes\Singleton;

#[Bind(IsScopedConcrete::class)]
#[Singleton]
interface IsSingleton
{
}
