<?php

namespace Voyager\Contracts\IOPools;

/**
 * Solicited mail: the result of work somebody asked for. Listen on this
 * interface to hear every completion regardless of wire.
 */
interface Completion extends QueuedIO
{
    public function ok(): bool;
}
