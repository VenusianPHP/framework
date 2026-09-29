<?php

namespace Voyager\Queue\Async;

use Voyager\Database\IOPools\WorkerConnection;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/**
 * A database queue's push, run in a pool worker on the caller's connection config: the row was
 * built when the push was made, payload and times included, so the worker only inserts it.
 */
final readonly class InsertJobRecord implements ShouldPool
{
    /**
     * @param array<string, mixed> $config the caller's connection config
     * @param array<string, mixed> $record
     */
    public function __construct(
        public string $connection,
        public array $config,
        public string $table,
        public array $record,
    ) {}

    public function handle(): mixed
    {
        return WorkerConnection::resolve($this->connection, $this->config)->table($this->table)->insertGetId($this->record);
    }
}
