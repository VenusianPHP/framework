<?php

namespace Tests\Vessel\Fixtures;

#[ContainerTestBootable]
final class ContainerTestHasBootable
{
    public bool $hasBooted = false;

    public function booting(): void
    {
        $this->hasBooted = true;
    }
}
