<?php

namespace Venusian\Tests\Queue\Fixtures;

use DateInterval;
use DateTimeInterface;
use Voyager\Contracts\Queue\Job;

/** The parts of the Queue contract a worker double never exercises. */
trait StubsQueueContract
{
    public function size(?string $queue = null): int
    {
        return 0;
    }

    public function push(object|string $job, mixed $data = '', ?string $queue = null): mixed
    {
        return null;
    }

    public function pushOn(string $queue, object|string $job, mixed $data = ''): mixed
    {
        return null;
    }

    public function pushRaw(string $payload, ?string $queue = null, array $options = []): mixed
    {
        return null;
    }

    public function later(DateInterval|DateTimeInterface|int $delay, object|string $job, mixed $data = '', ?string $queue = null): mixed
    {
        return null;
    }

    public function laterOn(string $queue, DateInterval|DateTimeInterface|int $delay, object|string $job, mixed $data = ''): mixed
    {
        return null;
    }

    public function bulk(array $jobs, mixed $data = '', ?string $queue = null): mixed
    {
        return null;
    }

    public function pop(?string $queue = null): ?Job
    {
        return null;
    }

    public function setConnectionName(string $name): static
    {
        $this->connectionName = $name;

        return $this;
    }
}
