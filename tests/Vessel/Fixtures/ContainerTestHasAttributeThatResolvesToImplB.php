<?php

namespace Tests\Vessel\Fixtures;

final class ContainerTestHasAttributeThatResolvesToImplB
{
    public function __construct(
        #[ContainerTestAttributeThatResolvesContractImpl('B')]
        public readonly ContainerTestContract $property
    ) {
    }
}
