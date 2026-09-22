<?php

namespace Voyager\Core\Bootstrap;

use Voyager\Contracts\Core\FrameworkCore;

class BootProviders
{
    /**
     * Bootstrap the given application.
     *
     * @param FrameworkCore $app
     * @return void
     */
    public function bootstrap(FrameworkCore $app): void
    {
        $app->boot();
    }
}