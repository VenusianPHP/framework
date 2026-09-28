<?php

namespace Voyager\Contracts\IOPools\WorkerPools;

use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\LoopResources\Wakeable;

/**
 * One worker, registered on the loop for as long as it lives. Its wakes() say what can
 * finish its current gig or end its life; woke() settles the promise or reports the death.
 */
interface PoolWorker extends Wakeable
{
    public function name(): string;

    public function busy(): bool;

    /**
     * False once the worker exited or was stopped, even if nobody on the loop has noticed yet.
     *
     * @return bool
     */
    public function alive(): bool;

    /** Sends the gig. The promise settles from woke() once the result or the death arrives. */
    public function assign(ShouldPool $gig, Promise $promise): void;

    /**
     * Ends the worker now and rejects its current gig. A pool calls this from Loop::onStop(),
     * when the loop has stopped turning and no result could arrive.
     */
    public function stop(): void;
}
