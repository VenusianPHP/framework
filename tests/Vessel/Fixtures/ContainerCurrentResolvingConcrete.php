<?php

namespace Tests\Vessel\Fixtures;

class ContainerCurrentResolvingConcrete
{
    public $currentlyResolving;

    public function __construct(
        #[ContainerCurrentResolvingAttribute]
        string $currentlyResolving
    ) {
        $this->currentlyResolving = $currentlyResolving;
    }
}
