<?php

namespace Tests\System\Stubs;

use Voyager\NutsAndBolts\ServiceProvider;

class ApplicationBindingsServiceProviderStub extends ServiceProvider
{
    public $bindings = [
        AbstractClass::class => ConcreteClass::class,
    ];
}
