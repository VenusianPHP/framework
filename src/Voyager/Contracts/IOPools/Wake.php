<?php

namespace Voyager\Contracts\IOPools;

interface Wake
{
    public function kind(): WakeReason;

    /** Same source, same key, every turn. The Waiter diffs on it. */
    public function key(): string;
}