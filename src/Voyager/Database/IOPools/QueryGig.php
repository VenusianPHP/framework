<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Voyager\Contracts\IOPools\ShouldPool;
use Voyager\Database\Instrument\Builder as InstrumentBuilder;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Query\Builder as QueryBuilder;

/**
 * A builder, a terminal, and its args. handle() runs the blocking terminal where the gig lands.
 * The builder serializes by connection name (Task 1), so the worker resolves its own connection.
 */
final class QueryGig implements ShouldPool
{
    public function __construct(
        public readonly QueryBuilder|InstrumentBuilder $builder,
        public readonly string $method,
        public readonly array $args = [],
    ) {}

    public function handle(): mixed
    {
        return Model::withoutRetrieved(fn () => $this->builder->{$this->method}(...$this->args));
    }
}
