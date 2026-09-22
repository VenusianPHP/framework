<?php
declare(strict_types=1);
namespace Voyager\Sketches;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\Contracts\Sketches\SketchRegistry as RegistryContract;
use Voyager\NutsAndBolts\ServiceProvider;

class SketchesServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/sketches.php', 'sketches');

        $this->app->registerSingleton('sketches.registry', fn (FrameworkCore $app) => new SketchRegistry($app));
        $this->app->alias('sketches.registry', SketchRegistry::class);
        $this->app->alias('sketches.registry', RegistryContract::class);

        $this->app->registerSingleton('sketches.runner', fn (FrameworkCore $app) => new SketchRunner($app['event-loop'], $app['config']));
        $this->app->alias('sketches.runner', SketchRunner::class);
    }

    public function provides(): array
    {
        return ['sketches.registry', SketchRegistry::class, RegistryContract::class, 'sketches.runner', SketchRunner::class];
    }
}
