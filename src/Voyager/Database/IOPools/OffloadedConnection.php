<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use BadMethodCallException;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\WorkTarget;

/** Raw statements on a named connection, as promises from a work target. */
final class OffloadedConnection
{
    public function __construct(private readonly string $connection, private readonly WorkTarget $target) {}

    public function __call(string $method, array $args): Promise
    {
        $allowed = array_map(static fn (OffloadableStatement $case) => $case->value, OffloadableStatement::cases());

        if (! in_array($method, $allowed, true)) {
            throw new BadMethodCallException("{$method}() does not cross a worker: a transaction, a cursor, or the PDO lives on one connection. Put the whole unit of work in a ShouldPool gig.");
        }

        return $this->target->run(new ConnectionGig($this->connection, $method, $args));
    }
}
