<?php

namespace Tests\Vessel\Fixtures;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class ContainerTestOnTenant
{
    public function __construct(
        public readonly Tenant $tenant
    ) {
    }
}
