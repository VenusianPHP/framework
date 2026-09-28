<?php

namespace Voyager\Contracts\IOPools\WorkerPools;

use Voyager\Contracts\IOPools\IOPoolsException;

/**
 * The gig threw inside its worker. Only strings cross back, so this carries the original's
 * class, message and trace instead of the exception itself.
 */
class RemoteException extends IOPoolsException
{
    public function __construct(
        public readonly string $remote_class,
        string $message,
        public readonly string $remote_trace,
    ) {
        parent::__construct("{$remote_class}: {$message}");
    }
}