<?php

declare(strict_types=1);

namespace Voyager\Core\Sketches;

use Voyager\Contracts\Core\FrameworkCore;

/**
 * Runs right after the configuration loads, before any provider can build the loop: a rocket run
 * hands the loop's mail to the running sketch, so the loop is built with the sketch mail handler.
 */
class DeliverMailToSketches
{
    public const string HANDLER_KEY = 'io-pools.event_loop.mail_handlers.default';

    public function bootstrap(FrameworkCore $app): void
    {
        $app['config']->set(self::HANDLER_KEY, 'sketch');
    }
}
