<?php

namespace Voyager\Contracts\IOPools\WorkerPools;

use Voyager\Contracts\Core\VenusianFrameworkException;
use Voyager\Contracts\IOPools\EventLoopException;

/**
 * The worker exited, crashed or never came up before its gig's result arrived.
 */
class DeadWorkerException extends EventLoopException
{

}