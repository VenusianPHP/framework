<?php

namespace Voyager\Database;

use Voyager\Vessel\Vessel;
use Voyager\Contracts\Database\LostConnectionDetector as LostConnectionDetectorContract;
use Throwable;

trait DetectsLostConnections
{
    /**
     * Determine if the given exception was caused by a lost connection.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function causedByLostConnection(Throwable $e)
    {
        $container = Vessel::getInstance();

        $detector = $container->bound(LostConnectionDetectorContract::class)
            ? $container[LostConnectionDetectorContract::class]
            : new LostConnectionDetector();

        return $detector->causedByLostConnection($e);
    }
}
