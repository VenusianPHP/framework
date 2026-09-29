<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Closure;
use Throwable;
use LogicException;
use InvalidArgumentException;
use Voyager\Vessel\ControlPanel;
use Voyager\Database\Connection;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\Contracts\IOPools\Loop;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Contracts\IOPools\WorkerPools\WorkerPool;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/**
 * Where a connection's offloaded calls go: a worker pool, ordered through the loop's
 * ConnectionLanes, each gig carrying the connection's name and config.
 */
final readonly class Offload
{
    /**
     * @param array<string, mixed> $config
     */
    private function __construct(
        public Loop $loop,
        public WorkerPool $pool,
        public ConnectionLanes $lanes,
        public string $connection,
        public array $config,
    ) {}

    /**
     * @param 'thread'|'process'|null $pool null: the thread workers when they are on, the process workers otherwise
     * @throws LogicException the connection can't be reached from a worker as it is
     * @throws InvalidArgumentException the pool isn't there or isn't on
     */
    public static function for(Connection $connection, ?string $pool): self
    {
        $name = $connection->getName();

        if (is_null($name)) {
            throw new LogicException('This connection has no name, so a worker could not find it. Resolve it through the database manager to offload it.');
        }

        if ($connection->getDriverName() === 'sqlite' && in_array($connection->getConfig('database'), [':memory:', ''], true)) {
            throw new LogicException("Connection [{$name}] is an in-memory SQLite database: a worker's connection would be a different, empty one.");
        }

        if ($connection->transactionLevel() > 0) {
            throw new LogicException("Connection [{$name}] has a transaction open: an offloaded call runs on the worker's own connection, outside it. Offload the whole transaction with via()->transaction().");
        }

        $app = ControlPanel::getInstance();

        $binding = match ($pool) {
            null => $app->isBound('thread-workers') ? 'thread-workers' : 'process-workers',
            'thread' => 'thread-workers',
            'process' => 'process-workers',
            default => throw new InvalidArgumentException(
                "There is no \"{$pool}\" pool: offload to 'thread' or 'process', or name none for the thread workers when they are on and the process workers otherwise."
            ),
        };

        if (! $app->isBound($binding)) {
            throw new InvalidArgumentException(match (true) {
                is_null($pool) => 'Offloading runs on a worker pool, and none is on: enable io-pools.pool_workers.threads or io-pools.pool_workers.process.',
                $pool === 'thread' => 'The thread workers are off: enable io-pools.pool_workers.threads to offload to them.',
                default => 'The process workers are off: enable io-pools.pool_workers.process to offload to them.',
            });
        }

        $loop = $app->get(Loop::class);

        return new self($loop, $app->get($binding), ConnectionLanes::for($loop), $name, Arr::except($connection->getConfig(), ['name']));
    }

    /**
     * Sends the gig once the connection's lanes let it through.
     *
     * @param Closure(): ShouldPool $gig builds the gig; building it now captures the call as it was made
     */
    public function send(bool $write, Closure $gig): Promise
    {
        try {
            $built = $gig();
        } catch (Throwable $e) {
            return $this->rejected(new InvalidArgumentException("This call can't be offloaded: {$e->getMessage()}", 0, $e));
        }

        return $this->lanes->run($this->connection, $write, fn (): Promise => $this->pool->submit($built));
    }

    public function rejected(Throwable $e): Promise
    {
        $promise = $this->loop->promise();
        $promise->reject($e);

        return $promise;
    }
}
