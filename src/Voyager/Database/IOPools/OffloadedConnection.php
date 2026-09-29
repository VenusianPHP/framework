<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Closure;
use BadMethodCallException;
use Voyager\Contracts\IOPools\Promise;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Raw statements on a named connection, as promises, and whole transactions run in a worker.
 * Selects run side by side; statements and transactions run alone, in call order.
 */
final readonly class OffloadedConnection
{
    public function __construct(private Offload $offload) {}

    /**
     * Runs $callback in a transaction on the worker's connection, which it receives. Commits or
     * rolls back there; the promise settles with what $callback returns.
     *
     * @param Closure(\Voyager\Database\Connection): mixed $callback
     */
    public function transaction(Closure $callback, int $attempts = 1): Promise
    {
        return $this->offload
            ->send(true, fn (): TransactionGig => new TransactionGig(
                $this->offload->connection, $this->offload->config, new SerializableClosure($callback), $attempts,
            ))
            ->then(Arrived::models(...));
    }

    /**
     * @param list<mixed> $args
     * @throws BadMethodCallException a method that isn't a raw statement
     */
    public function __call(string $method, array $args): Promise
    {
        $statement = OffloadableStatement::tryFrom($method)
            ?? throw new BadMethodCallException("{$method}() does not cross a worker: a cursor, or the PDO, lives on one connection. Offload a unit of work with via()->transaction().");

        return $this->offload->send($statement->writes(), function () use ($method, $args): ConnectionGig {
            serialize($args);

            return new ConnectionGig($this->offload->connection, $this->offload->config, $method, $args);
        });
    }
}
