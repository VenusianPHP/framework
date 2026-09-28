<?php

namespace Venusian\Tests\Log\Fixtures;

use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;
use Voyager\Contracts\IOPools\WorkerPools\WorkerPool;

/** Stands in for a pool: runs each gig right here and keeps it, so a test can see which pool got it. */
final class RecordingPool implements WorkerPool
{
    /** @var list<ShouldPool> */
    public array $gigs = [];

    public function __construct(private readonly Loop $loop) {}

    public function submit(ShouldPool $gig): Promise
    {
        $this->gigs[] = $gig;
        $promise = $this->loop->promise();
        $promise->resolve($gig->handle());

        return $promise;
    }

    public function warm(int $count): void {}

    public function workerCount(): int
    {
        return 0;
    }

    public function shutDown(): void {}
}
