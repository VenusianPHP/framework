<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;

#[Bind(WildcardConcrete::class)]
interface WildcardOnlyInterface
{
}
