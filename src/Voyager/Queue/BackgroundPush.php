<?php

namespace Voyager\Queue;

use Voyager\Contracts\IOPools\ShouldPool;

/** The gig a BackgroundQueue push becomes: on the worker, it is a plain sync push. */
final class BackgroundPush implements ShouldPool
{
    public function __construct(
        public readonly object|string $job,
        public readonly mixed $data,
        public readonly ?string $queue,
    ) {}

    public function handle(): mixed
    {
        return app('queue')->connection('sync')->push($this->job, $this->data, $this->queue);
    }
}
