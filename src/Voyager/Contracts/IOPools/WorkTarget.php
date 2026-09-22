<?php

namespace Voyager\Contracts\IOPools;

/** Somewhere a gig can run: now, next turn, a worker, a fork, a queue. The promise settles with handle()'s outcome. */
interface WorkTarget
{
    public function run(ShouldPool $gig): Promise;
}
