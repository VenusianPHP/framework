<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Give;

final class GiveTestComplex
{
    public function __construct(
        #[Give(ComplexDependency::class, ['param' => true])]
        public readonly ContainerTestContract $dependency
    ) {
    }
}
