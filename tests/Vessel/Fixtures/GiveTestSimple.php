<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Give;

final class GiveTestSimple
{
    public function __construct(
        #[Give(SimpleDependency::class)]
        public readonly ContainerTestContract $dependency
    ) {
    }
}
