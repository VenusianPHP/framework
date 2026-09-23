<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Ramsey\Uuid\Uuid;
use Voyager\Contracts\IOPools\Event;
use Voyager\NutsAndBolts\Collection;

/** One page of a streamed query. rows is an Instrument\Collection for a model query, a base Collection for a table query. */
final class ModelChunk extends Event
{
    private readonly string $uuid;

    public function __construct(
        public readonly string $connection,
        public readonly string $table,
        public readonly int $page,
        public readonly Collection $rows,
        public readonly bool $last,
    ) {
        $this->uuid = Uuid::uuid4()->toString();
    }

    public function name(): string { return 'model-chunk:'.$this->connection.':'.$this->table; }
    public function uuid(): string { return $this->uuid; }
    public function toData(): array { return ['connection' => $this->connection, 'table' => $this->table, 'page' => $this->page, 'rows' => $this->rows, 'last' => $this->last]; }
}
