<?php

namespace Voyager\Contracts\IOPools\WorkerPools;

use Voyager\Contracts\IOPools\Promise;

interface WorkerPool
{
    /**
     * Hands the gig to an idle worker, spawning one if there's room, or queues it.
     *
     * @param ShouldPool $gig
     * @return Promise
     */
    public function submit(ShouldPool $gig): Promise;

    /**
     * Spawns workers until $count are alive, so the first gigs don't pay for startup.
     *
     * @param int $count
     * @return void
     */
    public function warm(int $count): void;

    public function workerCount(): int;

    /**
     * Rejects queued gigs, stops every worker, and forgets them on the loop.
     *
     * @return void
     */
    public function shutDown(): void;
}