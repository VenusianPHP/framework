<?php

namespace Voyager\Queue;

use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;

/**
 * Runs the job on the loop's next turn, in this process. The push returns at once with a
 * promise of the job's outcome; the job runs after the caller's turn ends, so a handler that
 * queues never stalls its own frame.
 */
class DeferredQueue extends SyncQueue
{
    public function push(object|string $job, mixed $data = '', ?string $queue = null): Promise
    {
        return ($this->container ?? ControlPanel::getInstance())
            ->make(Loop::class)
            ->defer(fn () => parent::push($job, $data, $queue));
    }
}
