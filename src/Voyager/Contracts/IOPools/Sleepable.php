<?php

namespace Voyager\Contracts\IOPools;

interface Sleepable extends Tickable
{
    /**
     * Block for at most $budget_ms, waking early on anything of your own.
     * @return array<string> names of the resources that fired while asleep
     */
    public function sleep(int $budget_ms = 0): array;
}