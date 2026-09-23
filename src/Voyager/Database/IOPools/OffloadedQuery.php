<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use BadMethodCallException;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\WorkTarget;
use Voyager\Database\Instrument\Builder as InstrumentBuilder;
use Voyager\Database\Query\Builder as QueryBuilder;

/**
 * Every terminal on the builder, as a promise from a work target. The builder itself stays blocking.
 */
final class OffloadedQuery
{
    public function __construct(
        private readonly QueryBuilder|InstrumentBuilder $builder,
        private readonly WorkTarget $target,
    ) {}

    public function __call(string $method, array $args): Promise
    {
        if (in_array($method, array_map(static fn (RefusedQueryMethod $case) => $case->value, RefusedQueryMethod::cases()), true)) {
            throw new BadMethodCallException("{$method}() takes a callback or yields; neither crosses a worker. Use stream() for rows as loop mail.");
        }

        return $this->target->run(new QueryGig(clone $this->builder, $method, $args))->then(Arrived::models(...));
    }
}
