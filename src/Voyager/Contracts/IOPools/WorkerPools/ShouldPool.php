<?php

namespace Voyager\Contracts\IOPools\WorkerPools;

/**
 * A unit of work a pool runs off the loop's thread. It crosses into the worker serialized,
 * so it carries data, never open resources or closures.
 */
interface ShouldPool
{
    /** Runs inside the worker. The return value crosses back serialized and settles the promise. */
    public function handle(): mixed;
}