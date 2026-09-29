<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use LogicException;
use BadMethodCallException;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Database\Query\Builder as QueryBuilder;
use Voyager\Database\Instrument\Builder as InstrumentBuilder;

/**
 * Every terminal on the builder, as a promise: the builder is serialized as it stands, and the
 * terminal runs in a worker. Build the query first, then offload it: User::where(...)->via()->get().
 * Reads run side by side on a connection; anything else runs alone, in call order.
 */
final readonly class OffloadedQuery
{
    /** Terminals that only read, and may run beside other reads on the connection. */
    public const array READS = [
        'get', 'first', 'firstOr', 'firstOrFail', 'firstWhere', 'find', 'findOr', 'findOrFail', 'findMany', 'sole',
        'soleValue', 'value', 'rawValue', 'pluck', 'implode', 'count', 'min', 'max', 'sum', 'avg', 'average',
        'aggregate', 'numericAggregate', 'exists', 'doesntExist', 'existsOr', 'doesntExistOr', 'paginate',
        'simplePaginate', 'cursorPaginate', 'getCountForPagination', 'toSql', 'toRawSql',
    ];

    public function __construct(
        private QueryBuilder|InstrumentBuilder $builder,
        private Offload $offload,
    ) {}

    /**
     * @param list<mixed> $args
     * @throws BadMethodCallException a terminal that takes a callback or yields: stream() is the loop form
     */
    public function __call(string $method, array $args): Promise
    {
        if (! is_null(RefusedQueryMethod::tryFrom($method))) {
            throw new BadMethodCallException("{$method}() takes a callback or yields; neither crosses a worker. Use stream() for rows as loop mail.");
        }

        $builder = clone $this->builder;

        return $this->offload
            ->send(! in_array($method, self::READS, true), fn (): QueryGig => new QueryGig(
                $this->offload->connection, $this->offload->config, $method, serialize([$builder, $args]),
            ))
            ->then(function (mixed $result) use ($method): mixed {
                if ($result instanceof QueryBuilder || $result instanceof InstrumentBuilder) {
                    throw new LogicException("{$method}() builds the query rather than running it: build it first, then offload it, as in ->where(...)->via()->get().");
                }

                return Arrived::models($result);
            });
    }
}
