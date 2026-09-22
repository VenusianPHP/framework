<?php

namespace Voyager\Core\Providers;

use Voyager\NutsAndBolts\AggregateServiceProvider;
use Voyager\Contracts\NutsAndBolts\DeferrableProvider;

class ConsoleSupportServiceProvider extends AggregateServiceProvider implements DeferrableProvider
{
    /**
     * The provider class names.
     *
     * @var string[]
     */
    protected array $providers = [
        ComputerServiceProvider::class,
        //RocketServiceProvider::class,
        //MigrationServiceProvider::class,
        ComposerServiceProvider::class,
    ];
}