<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Voyager\NutsAndBolts\Collection;
use Voyager\Contracts\Signals\NamedSignal;

/**
 * One page of a stream()ed query, delivered as loop mail and dispatched as "db-chunk:{connection}:{table}".
 * rows is an Instrument Collection for a model query, a base Collection for a table query.
 */
final readonly class QueryChunk implements NamedSignal
{
    /**
     * @param int $page 1 for the first page
     * @param bool $last no page follows this one
     */
    public function __construct(
        public string $connection,
        public string $table,
        public int $page,
        public Collection $rows,
        public bool $last,
    ) {}

    public function name(): string
    {
        return "db-chunk:{$this->connection}:{$this->table}";
    }
}
