<?php

namespace Voyager\System\Bootstrap;

use Voyager\Contracts\System\Application;

class BootProviders
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Voyager\Contracts\System\Application  $app
     * @return void
     */
    public function bootstrap(Application $app): void
    {
        $app->boot();
    }
}
