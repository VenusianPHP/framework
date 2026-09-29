<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Voyager\Database\Instrument\Model;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/**
 * A builder, a terminal, and its arguments, run where the gig lands. The builder and arguments
 * are serialized when the call is made, so later changes to either don't reach the worker, and
 * the builder is only rebuilt once the worker's connection runs on the caller's config.
 */
final readonly class QueryGig implements ShouldPool
{
    /**
     * @param array<string, mixed> $config the caller's connection config
     * @param string $payload serialize([$builder, $args])
     */
    public function __construct(
        public string $connection,
        public array $config,
        public string $method,
        public string $payload,
    ) {}

    public function handle(): mixed
    {
        WorkerConnection::resolve($this->connection, $this->config);

        [$builder, $args] = unserialize($this->payload);

        // retrieved listeners live in the caller: Arrived fires them once the models land there
        return Model::withoutRetrieved(fn () => $builder->{$this->method}(...$args));
    }
}
