<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Voyager\Database\Instrument\Model;
use Laravel\SerializableClosure\SerializableClosure;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/**
 * A whole unit of work, run in a transaction on the worker's connection: the closure gets that
 * connection, and what it returns is the gig's result. It commits or rolls back where it runs.
 */
final readonly class TransactionGig implements ShouldPool
{
    /**
     * @param array<string, mixed> $config the caller's connection config
     */
    public function __construct(
        public string $connection,
        public array $config,
        public SerializableClosure $callback,
        public int $attempts = 1,
    ) {}

    public function handle(): mixed
    {
        $connection = WorkerConnection::resolve($this->connection, $this->config);

        return Model::withoutRetrieved(fn () => $connection->transaction($this->callback->getClosure(), $this->attempts));
    }
}
