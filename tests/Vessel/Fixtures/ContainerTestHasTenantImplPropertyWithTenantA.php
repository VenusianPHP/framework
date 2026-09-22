<?php

namespace Venusian\Tests\Vessel\Fixtures;

final class ContainerTestHasTenantImplPropertyWithTenantA
{
    public function __construct(
        #[ContainerTestOnTenant(Tenant::TenantA)]
        public readonly HasTenantImpl $property
    ) {
    }
}
