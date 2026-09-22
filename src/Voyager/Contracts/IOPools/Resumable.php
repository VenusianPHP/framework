<?php

namespace Voyager\Contracts\IOPools;

/**
 * Walked once per turn AFTER promises flush and BEFORE pump: a wait that settled
 * this turn is picked up this turn.
 */
interface Resumable extends Sourceable
{
    /** True if anything ran. The loop flushes and asks again until nothing does. */
    public function resume(): bool;
}
