<?php

namespace Tests\Vessel\Fixtures;

final class HasTenantImpl
{
    public ?Tenant $tenant = null;

    public function onTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }
}
