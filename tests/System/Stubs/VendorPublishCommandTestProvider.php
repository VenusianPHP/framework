<?php

namespace Tests\System\Stubs;

use Voyager\NutsAndBolts\ServiceProvider;

class VendorPublishCommandTestProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->publishes([
            $this->app->basePath('src/windows.php') => $this->app->configPath('windows.php'),
        ]);
    }
}
