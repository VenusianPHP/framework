<?php

namespace Voyager\Contracts\IOPools\LoopResources;

interface Sleepable extends Tickable
{
    /**
     * Block at most $budget_ns inside your own wait. tick() runs right after.
     */
    public function sleep(int $budget_ns): void;
}