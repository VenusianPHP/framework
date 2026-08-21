<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Bind;

#[Bind(FallbackConcrete::class)]
#[Bind(ProdConcrete::class, environments: 'prod')]
interface WildcardAndProdInterface
{
}
