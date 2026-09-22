<?php

namespace Voyager\Contracts\IOPools;

/** A worker that is its own OS process. Threads have no pid, so they stay plain PoolWorkers. */
interface ProcessWorker extends PoolWorker
{
    /** Cached at spawn: still answerable after close(). */
    public function pid(): int;
}
