<?php

namespace Voyager\Database;

use Voyager\Vessel\Vessel;
use Voyager\Contracts\Database\ConcurrencyErrorDetector as ConcurrencyErrorDetectorContract;
use Throwable;

trait DetectsConcurrencyErrors
{
    /**
     * Determine if the given exception was caused by a concurrency error such as a deadlock or serialization failure.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function causedByConcurrencyError(Throwable $e)
    {
        $container = Vessel::getInstance();

        $detector = $container->bound(ConcurrencyErrorDetectorContract::class)
            ? $container[ConcurrencyErrorDetectorContract::class]
            : new ConcurrencyErrorDetector();

        return $detector->causedByConcurrencyError($e);
    }
}
