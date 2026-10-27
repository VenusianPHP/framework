<?php

namespace Venusian\Tests\Vessel\Fixtures;

use Voyager\Contracts\Vessel\Attributes\Bind;
use Voyager\Contracts\Vessel\Attributes\Scoped;

/** Bound to its concrete and scoped through attributes, the registerScoped() path. */
#[Bind(BoundScopedConcrete::class)]
#[Scoped]
interface BoundScopedContract
{
}
