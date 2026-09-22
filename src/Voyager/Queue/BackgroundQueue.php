<?php

namespace Voyager\Queue;

use Voyager\Contracts\IOPools\WorkerPool;
use Voyager\Vessel\ControlPanel;

/**
 * Runs the job on a worker-pool process or thread. The push returns a promise that settles
 * with the job's outcome; nothing about the job leaves this machine.
 */
class BackgroundQueue extends SyncQueue
{
    public function push(object|string $job, mixed $data = '', ?string $queue = null): mixed
    {
        return ($this->container ?? ControlPanel::getInstance())
            ->make(WorkerPool::class)
            ->submit(new BackgroundPush($job, $data, $queue));
    }
}
