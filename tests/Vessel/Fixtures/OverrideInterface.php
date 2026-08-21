<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;

#[Bind(OriginalConcrete::class)]
interface OverrideInterface
{
}
