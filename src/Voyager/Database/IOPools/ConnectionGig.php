<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/** A raw statement on the named connection, run where the gig lands. */
final readonly class ConnectionGig implements ShouldPool
{
    /**
     * @param array<string, mixed> $config the caller's connection config
     * @param list<mixed> $args
     */
    public function __construct(
        public string $connection,
        public array $config,
        public string $method,
        public array $args = [],
    ) {}

    public function handle(): mixed
    {
        return WorkerConnection::resolve($this->connection, $this->config)->{$this->method}(...$this->args);
    }
}
