<?php

namespace Voyager\System\Providers;

use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\NutsAndBolts\AggregateServiceProvider;

class ConsoleSupportServiceProvider extends AggregateServiceProvider implements DeferrableProvider
{
    /**
     * The provider class names.
     *
     * @var string[]
     */
    protected array $providers = [
        ComputerServiceProvider::class,
        // MigrationServiceProvider::class,   // lands with Database in wave 6
        ComposerServiceProvider::class,
    ];
}
