<?php

namespace Voyager\Sketches;

use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\Contracts\Sketches\SketchRegistry as SketchRegistryContract;
use Voyager\NutsAndBolts\ServiceProvider;

class SketchesServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Application base class used for conventional discovery under app/Runner/Sketches.
     */
    protected string $appSketchBaseClass = \App\Runner\Sketches\Sketch::class;

    public function register(): void
    {
        $this->app->singleton(SketchRegistry::class, function ($app) {
            return new SketchRegistry($app);
        });

        $this->app->singleton(SketchRegistryContract::class, function ($app) {
            return $app->make(SketchRegistry::class);
        });

        $this->app->singleton('sketch', function ($app) {
            return $app->make(SketchRegistry::class);
        });

        $this->app->singleton(SketchRunner::class, fn () => new SketchRunner);

        $this->app->singleton('sketch.runner', function ($app) {
            return $app->make(SketchRunner::class);
        });
    }

    public function boot(): void
    {
        $this->discoverAppSketches();
        $this->registerConfiguredSketches();
    }

    /**
     * Scan app/Runner/Sketches for concrete subclasses of the application Sketch base.
     */
    protected function discoverAppSketches(): void
    {
        $path = $this->app->path('Runner/Sketches');

        if (! is_dir($path)) {
            return;
        }

        /** @var SketchRegistry $registry */
        $registry = $this->app->make(SketchRegistry::class);

        foreach (DiscoverSketches::within(
            $path,
            $this->app->basePath(),
            $this->appSketchBaseClass,
            $this->app->getNamespace(),
            $this->app->path(),
        ) as $name => $class) {
            $registry->registerConvention($name, $class);
        }
    }

    /**
     * Register attributed Sketch classes listed in config/sketches.php.
     */
    protected function registerConfiguredSketches(): void
    {
        $classes = $this->app['config']->get('sketches.load', []);

        if (! is_array($classes) || $classes === []) {
            return;
        }

        /** @var SketchRegistry $registry */
        $registry = $this->app->make(SketchRegistry::class);

        foreach ($classes as $class) {
            if (! is_string($class) || $class === '') {
                continue;
            }

            $registry->register($class);
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'sketch',
            'sketch.runner',
            SketchRegistry::class,
            SketchRegistryContract::class,
            SketchRunner::class,
        ];
    }
}
