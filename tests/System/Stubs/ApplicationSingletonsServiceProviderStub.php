<?php

namespace Tests\System\Stubs;

use Voyager\NutsAndBolts\ServiceProvider;

class ApplicationSingletonsServiceProviderStub extends ServiceProvider
{
    public $singletons = [
        NonContractBackedClass::class,
        AbstractClass::class => ConcreteClass::class,
    ];
}
