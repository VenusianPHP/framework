<?php

use Tests\Vessel\Fixtures\ContainerTestBootable;
use Tests\Vessel\Fixtures\ContainerTestConfiguresClass;
use Tests\Vessel\Fixtures\ContainerTestHasBootable;
use Tests\Vessel\Fixtures\ContainerTestHasSelfConfiguringAttributeAndConstructor;
use Tests\Vessel\Fixtures\ContainerTestHasTenantImplPropertyWithTenantA;
use Tests\Vessel\Fixtures\ContainerTestHasTenantImplPropertyWithTenantB;
use Tests\Vessel\Fixtures\ContainerTestOnTenant;
use Tests\Vessel\Fixtures\HasTenantImpl;
use Tests\Vessel\Fixtures\Tenant;
use Voyager\Vessel\Vessel;

test('the callback runs after a dependency carrying the attribute is resolved', function () {
    $vessel = new Vessel;

    $vessel->afterResolvingAttribute(ContainerTestOnTenant::class, function (ContainerTestOnTenant $attribute, HasTenantImpl $hasTenantImpl, Vessel $vessel) {
        $hasTenantImpl->onTenant($attribute->tenant);
    });

    $hasTenantA = $vessel->make(ContainerTestHasTenantImplPropertyWithTenantA::class);

    expect($hasTenantA->property)->toBeInstanceOf(HasTenantImpl::class)
        ->and($hasTenantA->property->tenant)->toEqual(Tenant::TenantA);

    $hasTenantB = $vessel->make(ContainerTestHasTenantImplPropertyWithTenantB::class);

    expect($hasTenantB->property)->toBeInstanceOf(HasTenantImpl::class)
        ->and($hasTenantB->property->tenant)->toEqual(Tenant::TenantB);
});

test('the callback runs after a class carrying the attribute is resolved', function () {
    $vessel = new Vessel;

    $vessel->afterResolvingAttribute(
        ContainerTestBootable::class,
        fn ($_, $instance, Vessel $vessel) => method_exists($instance, 'booting') && $vessel->call([$instance, 'booting'])
    );

    $instance = $vessel->make(ContainerTestHasBootable::class);

    expect($instance)->toBeInstanceOf(ContainerTestHasBootable::class)
        ->and($instance->hasBooted)->toBeTrue();
});

test('the callback wins over contextual binding for a class with a constructor', function () {
    $vessel = new Vessel;

    $vessel->afterResolvingAttribute(ContainerTestConfiguresClass::class, function (ContainerTestConfiguresClass $attribute, $class) {
        $class->value = $attribute->value;
    });

    $vessel->when(ContainerTestHasSelfConfiguringAttributeAndConstructor::class)
        ->needs('$value')
        ->give('no-the-right-value');

    $instance = $vessel->make(ContainerTestHasSelfConfiguringAttributeAndConstructor::class);

    expect($instance)->toBeInstanceOf(ContainerTestHasSelfConfiguringAttributeAndConstructor::class)
        ->and($instance->value)->toEqual('the-right-value');
});

test('the callback runs for attributes on a called closure', function () {
    $vessel = new Vessel;

    $vessel->afterResolvingAttribute(ContainerTestOnTenant::class, function (ContainerTestOnTenant $attribute, HasTenantImpl $hasTenantImpl, Vessel $vessel) {
        $hasTenantImpl->onTenant($attribute->tenant);
    });

    $tenant = $vessel->call(function (#[ContainerTestOnTenant(Tenant::TenantA)] HasTenantImpl $property) {
        return $property->tenant;
    });

    expect($tenant)->toEqual(Tenant::TenantA);
});
