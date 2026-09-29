<?php

namespace Voyager\Queue\Async;

use Closure;
use Throwable;
use DateInterval;
use DateTimeInterface;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\Queue\Queue as QueueContract;

/**
 * A queue connection's pushes as promises of the job's id. A queue that does its own I/O
 * (redis, database) pushes without blocking the loop; any other runs its push right here, and
 * a push that already answers with a promise (background, deferred) hands that promise back.
 */
final class AsyncQueue
{
    public function __construct(
        private readonly QueueContract $queue,
        private readonly Loop $loop,
    ) {}

    public function push(object|string $job, mixed $data = '', ?string $queue = null): Promise
    {
        return $this->queue instanceof PushesAsync
            ? $this->queue->pushAsync($job, $data, $queue)
            : $this->inline(fn () => $this->queue->push($job, $data, $queue));
    }

    public function pushOn(string $queue, object|string $job, mixed $data = ''): Promise
    {
        return $this->push($job, $data, $queue);
    }

    public function later(DateInterval|DateTimeInterface|int $delay, object|string $job, mixed $data = '', ?string $queue = null): Promise
    {
        return $this->queue instanceof PushesAsync
            ? $this->queue->laterAsync($delay, $job, $data, $queue)
            : $this->inline(fn () => $this->queue->later($delay, $job, $data, $queue));
    }

    public function laterOn(string $queue, DateInterval|DateTimeInterface|int $delay, object|string $job, mixed $data = ''): Promise
    {
        return $this->later($delay, $job, $data, $queue);
    }

    public function pushRaw(string $payload, ?string $queue = null, array $options = []): Promise
    {
        return $this->queue instanceof PushesAsync
            ? $this->queue->pushRawAsync($payload, $queue, $options)
            : $this->inline(fn () => $this->queue->pushRaw($payload, $queue, $options));
    }

    private function inline(Closure $push): Promise
    {
        try {
            $result = $push();
        } catch (Throwable $e) {
            $promise = $this->loop->promise();
            $promise->reject($e);

            return $promise;
        }

        if ($result instanceof Promise) {
            return $result;
        }

        $promise = $this->loop->promise();
        $promise->resolve($result);

        return $promise;
    }
}
