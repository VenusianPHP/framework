<?php

use Venusian\Tests\Vessel\Fixtures\ContainerTestBootable;
use Venusian\Tests\Vessel\Fixtures\ContainerTestConfiguresClass;
use Venusian\Tests\Vessel\Fixtures\ContainerTestHasBootable;
use Venusian\Tests\Vessel\Fixtures\ContainerTestHasSelfConfiguringAttributeAndConstructor;
use Venusian\Tests\Vessel\Fixtures\ContainerTestHasTenantImplPropertyWithTenantA;
use Venusian\Tests\Vessel\Fixtures\ContainerTestHasTenantImplPropertyWithTenantB;
use Venusian\Tests\Vessel\Fixtures\ContainerTestOnTenant;
use Venusian\Tests\Vessel\Fixtures\HasTenantImpl;
use Venusian\Tests\Vessel\Fixtures\Tenant;
use Voyager\Vessel\ControlPanel;

test('the callback runs after a dependency carrying the attribute is resolved', function () {
    $vessel = new ControlPanel;

    $vessel->afterResolvingAttribute(ContainerTestOnTenant::class, function (ContainerTestOnTenant $attribute, HasTenantImpl $hasTenantImpl, ControlPanel $vessel) {
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
    $vessel = new ControlPanel;

    $vessel->afterResolvingAttribute(
        ContainerTestBootable::class,
        fn ($_, $instance, ControlPanel $vessel) => method_exists($instance, 'booting') && $vessel->call([$instance, 'booting'])
    );

    $instance = $vessel->make(ContainerTestHasBootable::class);

    expect($instance)->toBeInstanceOf(ContainerTestHasBootable::class)
        ->and($instance->hasBooted)->toBeTrue();
});

test('the callback wins over contextual binding for a class with a constructor', function () {
    $vessel = new ControlPanel;

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
    $vessel = new ControlPanel;

    $vessel->afterResolvingAttribute(ContainerTestOnTenant::class, function (ContainerTestOnTenant $attribute, HasTenantImpl $hasTenantImpl, ControlPanel $vessel) {
        $hasTenantImpl->onTenant($attribute->tenant);
    });

    $tenant = $vessel->call(function (#[ContainerTestOnTenant(Tenant::TenantA)] HasTenantImpl $property) {
        return $property->tenant;
    });

    expect($tenant)->toEqual(Tenant::TenantA);
});
