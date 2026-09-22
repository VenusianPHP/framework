<?php

namespace Voyager\Contracts\IOPools;

interface WorkerPool
{
    public function shutDown(): void;
    public function submit(ShouldPool $gig): Promise;

    /** Spawn up to $count workers ahead of any gig. Capped at the pool's size. */
    public function warm(int $count): void;
}