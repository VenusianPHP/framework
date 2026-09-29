<?php

namespace Voyager\Queue;

use InvalidArgumentException;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\WorkerPools\WorkerPool;

/**
 * Runs the job on a worker-pool process or thread. The push returns a promise that settles
 * with the job's outcome; nothing about the job leaves this machine.
 */
class BackgroundQueue extends SyncQueue
{
    /**
     * @param 'thread'|'process'|null $pool null: the thread workers when they are on, the process workers otherwise
     */
    public function __construct(bool $dispatchAfterCommit = false, protected ?string $pool = null)
    {
        parent::__construct($dispatchAfterCommit);
    }

    public function push(object|string $job, mixed $data = '', ?string $queue = null): Promise
    {
        return $this->workerPool()->submit(new BackgroundPush($job, $data, $queue));
    }

    /**
     * @throws InvalidArgumentException the pool isn't there or isn't on
     */
    protected function workerPool(): WorkerPool
    {
        $app = $this->container ?? ControlPanel::getInstance();

        $binding = match ($this->pool) {
            null => $app->isBound('thread-workers') ? 'thread-workers' : 'process-workers',
            'thread' => 'thread-workers',
            'process' => 'process-workers',
            default => throw new InvalidArgumentException(
                "There is no \"{$this->pool}\" pool: set the background connection's pool to 'thread' or 'process', or leave it out for the thread workers when they are on and the process workers otherwise."
            ),
        };

        if (! $app->isBound($binding)) {
            throw new InvalidArgumentException(match (true) {
                is_null($this->pool) => 'The background queue runs jobs on a worker pool, and none is on: enable io-pools.pool_workers.threads or io-pools.pool_workers.process.',
                $this->pool === 'thread' => 'The thread workers are off: enable io-pools.pool_workers.threads to run background jobs on them.',
                default => 'The process workers are off: enable io-pools.pool_workers.process to run background jobs on them.',
            });
        }

        return $app->get($binding);
    }
}
