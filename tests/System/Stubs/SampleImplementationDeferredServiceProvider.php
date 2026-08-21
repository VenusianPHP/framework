<?php

namespace Tests\System\Stubs;

use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\NutsAndBolts\ServiceProvider;

class SampleImplementationDeferredServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->when(SampleImplementation::class)->needs('$primitive')->give(function () {
            return 'foo';
        });
    }
}
