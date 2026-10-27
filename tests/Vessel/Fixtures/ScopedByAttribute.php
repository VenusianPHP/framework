<?php

namespace Venusian\Tests\Vessel\Fixtures;

use Voyager\Contracts\Vessel\Attributes\Scoped;

/** Scoped by its attribute alone, with no binding registered for it. */
#[Scoped]
class ScopedByAttribute
{
}
