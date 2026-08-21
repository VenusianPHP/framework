<?php

namespace Tests\Vessel\Fixtures;

final class ContainerTestHasTenantImplPropertyWithTenantB
{
    public function __construct(
        #[ContainerTestOnTenant(Tenant::TenantB)]
        public readonly HasTenantImpl $property
    ) {
    }
}
