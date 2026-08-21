<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;
use Voyager\Vessel\Attributes\Scoped;

#[Bind(IsScopedConcrete::class)]
#[Scoped]
interface IsScoped
{
}
