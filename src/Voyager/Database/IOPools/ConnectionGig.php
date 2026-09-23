<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Voyager\Contracts\IOPools\ShouldPool;

/** A connection name, a method, and its args. The worker does app('db')->connection($name). */
final class ConnectionGig implements ShouldPool
{
    public function __construct(
        public readonly string $connection,
        public readonly string $method,
        public readonly array $args = [],
    ) {}

    public function handle(): mixed
    {
        return app('db')->connection($this->connection)->{$this->method}(...$this->args);
    }
}
