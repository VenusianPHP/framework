<?php

namespace Voyager\Core\Bootstrap;

use Voyager\Contracts\Core\FrameworkCore;

class DeliverMailToSignals
{
    public function bootstrap(FrameworkCore $app): void
    {
        $app['config']->set('io-pools.event_loop.mail_handlers.default', 'signal');
    }
}