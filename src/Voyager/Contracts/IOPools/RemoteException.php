<?php

namespace Voyager\Contracts\IOPools;

use Voyager\Contracts\Core\VenusianFrameworkException;

class RemoteException extends EventLoopException
{
    public function __construct(
        public readonly string $remote_class,
        string $message,
        public readonly string $remote_trace,
    ) {
        parent::__construct("[{$remote_class}] {$message}");
    }
}