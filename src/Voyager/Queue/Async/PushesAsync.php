<?php

namespace Voyager\Queue\Async;

use DateInterval;
use DateTimeInterface;
use Voyager\Contracts\IOPools\Promise;

/**
 * A queue whose push does I/O of its own, and can do it without blocking the loop. Each
 * promise settles with the pushed job's id. The payload is built when the call is made.
 */
interface PushesAsync
{
    public function pushAsync(object|string $job, mixed $data = '', ?string $queue = null): Promise;

    public function laterAsync(DateInterval|DateTimeInterface|int $delay, object|string $job, mixed $data = '', ?string $queue = null): Promise;

    public function pushRawAsync(string $payload, ?string $queue = null, array $options = []): Promise;
}
