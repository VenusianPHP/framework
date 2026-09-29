<?php

namespace Voyager\Queue;

use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/** The gig a BackgroundQueue push becomes: on the worker, it is a plain sync push. */
final readonly class BackgroundPush implements ShouldPool
{
    public function __construct(
        public object|string $job,
        public mixed $data,
        public ?string $queue,
    ) {}

    public function handle(): mixed
    {
        return app('queue')->connection('sync')->push($this->job, $this->data, $this->queue);
    }
}
