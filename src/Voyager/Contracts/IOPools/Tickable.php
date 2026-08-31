<?php

namespace Voyager\Contracts\IOPools;

/**
 * Something a loop owner pumps once per tick.
 *
 * The one law of the component: tick() returns fast, always. The loop's
 * budget belongs to whoever owns the loop; a tickable that waits has
 * broken the contract, not stretched it.
 */
interface Tickable
{
    public function tick(): void;
}
