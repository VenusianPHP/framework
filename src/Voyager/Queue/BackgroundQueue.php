<?php

namespace Voyager\Queue;

use Voyager\NutsAndBolts\MagicAliases\Concurrency;

class BackgroundQueue extends SyncQueue
{
    /**
     * Push a new job onto the queue.
     *
     * @param  string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     *
     * @throws \Throwable
     */
    public function push($job, $data = '', $queue = null)
    {
        Concurrency::driver('process')->defer(
            fn () => \Voyager\NutsAndBolts\MagicAliases\Queue::connection('sync')->push($job, $data, $queue)
        );
    }
}
