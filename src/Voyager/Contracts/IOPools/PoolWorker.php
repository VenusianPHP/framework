<?php

namespace Voyager\Contracts\IOPools;

interface PoolWorker extends StreamWatchable
{
    public function name(): string;

    public function busy(): bool;

    /**
     * Hand the worker a gig. The promise settles when the worker answers.
     * Never throws because of the gig: one that can't travel rejects the promise and frees the worker.
     */
    public function give(ShouldPool $gig, Promise $promise): void;

    /** End the worker at once: close the pipes and the process, or kill the runtime and the bell. */
    public function close(): void;

    /** An idle worker is unwatched, so this is how the pool notices it died. Process: proc_get_status(); thread: not closed. */
    public function alive(): bool;

    /** Reject the current gig with StoppedPoolException, then close(). */
    public function stop(): void;

    /** Gigs this worker has finished since it spawned. */
    public function jobsDone(): int;

    /**
     * Planned exit after max_jobs. Process: close stdin and stay registered until its EOF.
     * Thread: the runtime is idle, so it leaves the pool at once through Pool::retired().
     */
    public function retire(): void;

    public function retiring(): bool;
}
