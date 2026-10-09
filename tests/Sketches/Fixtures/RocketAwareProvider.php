<?php

namespace Venusian\Tests\Sketches\Fixtures;

use Voyager\NutsAndBolts\ServiceProvider;

/** Records what isRocketRunning() said while providers were registering. */
final class RocketAwareProvider extends ServiceProvider
{
    public static ?bool $saw_rocket = null;

    public function register(): void
    {
        self::$saw_rocket = $this->app->isRocketRunning();
    }
}
