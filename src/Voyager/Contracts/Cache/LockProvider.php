<?php

namespace Voyager\Contracts\Cache;

interface LockProvider
{
    /**
     * Get a lock instance.
     *
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return \Voyager\Contracts\Cache\Lock
     */
    public function lock(string $name, int $seconds = 0, ?string $owner = null);

    /**
     * Restore a lock instance using the owner identifier.
     *
     * @param string $name
     * @param string $owner
     * @return \Voyager\Contracts\Cache\Lock
     */
    public function restoreLock(string $name, string $owner): Lock;
}
