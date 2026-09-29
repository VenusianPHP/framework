<?php
declare(strict_types=1);
namespace Voyager\Sketches;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\Contracts\Sketches\SketchRegistry as RegistryContract;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\IOPools\MailHandlers\SketchMailHandler;

class SketchesServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {

        $this->app->registerSingleton('sketches.registry', fn (FrameworkCore $app) => new SketchRegistry($app));
        $this->app->alias('sketches.registry', SketchRegistry::class);
        $this->app->alias('sketches.registry', RegistryContract::class);

        $this->app->registerSingleton('sketches.runner', function (FrameworkCore $app): SketchRunner {
            $loop = $app['event-loop'];
            // The handler the loop was built with: the manager hands every ask the same driver.
            $mail = $app['mail-handler-mgr']->driver();

            return new SketchRunner($loop, $app['config'], $mail instanceof SketchMailHandler ? $mail : null);
        });
        $this->app->alias('sketches.runner', SketchRunner::class);
    }

    public function provides(): array
    {
        return ['sketches.registry', SketchRegistry::class, RegistryContract::class, 'sketches.runner', SketchRunner::class];
    }
}
