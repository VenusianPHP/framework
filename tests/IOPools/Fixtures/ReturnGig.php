<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/** Hands back its value, and who ran it, after an optional nap. */
final class ReturnGig implements ShouldPool
{
    public function __construct(public readonly mixed $value = null, public readonly int $sleep_ms = 0) {}

    public function handle(): mixed
    {
        usleep($this->sleep_ms * 1000);

        return ['value' => $this->value, 'pid' => getmypid(), 'thread' => function_exists('zend_thread_id') ? zend_thread_id() : 0];
    }
}
