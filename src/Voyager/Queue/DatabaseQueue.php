<?php

namespace Voyager\Queue;

use PDO;
use Throwable;
use Voyager\Contracts\IOPools\Promise;
use Voyager\Queue\Async\PushesAsync;
use Voyager\Queue\Async\InsertJobRecord;
use Voyager\Database\IOPools\Offload;
use DateInterval;
use DateTimeInterface;
use Voyager\Database\Connection;
use Voyager\Contracts\Queue\Job;
use Voyager\Queue\Jobs\DatabaseJob;
use Voyager\NutsAndBolts\Collection;
use Voyager\Queue\Jobs\DatabaseJobRecord;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\Contracts\Queue\ClearableQueue;
use Voyager\Contracts\Queue\Queue as QueueContract;

/**
 * Jobs as rows in a table: a pop reserves the oldest available row inside a transaction, taking
 * it with SKIP LOCKED where the server has it so workers never wait on each other's rows, and a
 * row reserved longer than retry_after is taken back as if it were never popped.
 */
class DatabaseQueue extends Queue implements QueueContract, ClearableQueue, PushesAsync
{
    public function __construct(
        protected Connection $database,
        protected string $table,
        protected string $default = 'default',
        protected int $retryAfter = 60,
        bool $dispatchAfterCommit = false,
    ) {
        $this->dispatchAfterCommit = $dispatchAfterCommit;
    }

    public function size(?string $queue = null): int
    {
        return $this->database->table($this->table)->where('queue', $this->getQueue($queue))->count();
    }

    public function pendingSize(?string $queue = null): int
    {
        return $this->database->table($this->table)
            ->where('queue', $this->getQueue($queue))
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $this->currentTime())
            ->count();
    }

    public function delayedSize(?string $queue = null): int
    {
        return $this->database->table($this->table)
            ->where('queue', $this->getQueue($queue))
            ->whereNull('reserved_at')
            ->where('available_at', '>', $this->currentTime())
            ->count();
    }

    public function reservedSize(?string $queue = null): int
    {
        return $this->database->table($this->table)
            ->where('queue', $this->getQueue($queue))
            ->whereNotNull('reserved_at')
            ->count();
    }

    public function creationTimeOfOldestPendingJob(?string $queue = null): ?int
    {
        $available = $this->database->table($this->table)
            ->where('queue', $this->getQueue($queue))
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $this->currentTime())
            ->oldest('available_at')
            ->value('available_at');

        return is_null($available) ? null : (int) $available;
    }

    public function push(object|string $job, mixed $data = '', ?string $queue = null): mixed
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            fn (string $payload, ?string $queue) => $this->pushToDatabase($queue, $payload),
        );
    }

    public function pushRaw(string $payload, ?string $queue = null, array $options = []): mixed
    {
        return $this->pushToDatabase($queue, $payload);
    }

    public function later(DateInterval|DateTimeInterface|int $delay, object|string $job, mixed $data = '', ?string $queue = null): mixed
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data, $delay),
            $queue,
            $delay,
            fn (string $payload, ?string $queue, DateInterval|DateTimeInterface|int $delay) => $this->pushToDatabase($queue, $payload, $delay),
        );
    }

    public function bulk(array $jobs, mixed $data = '', ?string $queue = null): mixed
    {
        $queue = $this->getQueue($queue);
        $now = $this->availableAt();

        return $this->database->table($this->table)->insert((new Collection($jobs))->map(
            fn (object|string $job): array => $this->buildDatabaseRecord(
                $queue,
                $this->createPayload($job, $queue, $data),
                isset($job->delay) ? $this->availableAt($job->delay) : $now,
            )
        )->all());
    }

    public function pushAsync(object|string $job, mixed $data = '', ?string $queue = null): Promise
    {
        return $this->enqueueUsingAsync(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            fn (string $payload, ?string $queue): Promise => $this->pushToDatabaseAsync($queue, $payload),
        );
    }

    public function laterAsync(DateInterval|DateTimeInterface|int $delay, object|string $job, mixed $data = '', ?string $queue = null): Promise
    {
        return $this->enqueueUsingAsync(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data, $delay),
            $queue,
            $delay,
            fn (string $payload, ?string $queue, DateInterval|DateTimeInterface|int $delay): Promise => $this->pushToDatabaseAsync($queue, $payload, $delay),
        );
    }

    public function pushRawAsync(string $payload, ?string $queue = null, array $options = []): Promise
    {
        return $this->pushToDatabaseAsync($queue, $payload);
    }

    /**
     * The insert as a gig, offloaded like any write on the connection: it waits for the calls
     * made before it there, and a blocking query after it waits for it. The row, available_at
     * included, is built now, so a delay counts from the call.
     */
    protected function pushToDatabaseAsync(?string $queue, string $payload, DateInterval|DateTimeInterface|int $delay = 0): Promise
    {
        $record = $this->buildDatabaseRecord($this->getQueue($queue), $payload, $this->availableAt($delay));

        try {
            $offload = Offload::for($this->database, null);
        } catch (Throwable $e) {
            $promise = $this->container->make(\Voyager\Contracts\IOPools\Loop::class)->promise();
            $promise->reject($e);

            return $promise;
        }

        return $offload->send(true, fn (): InsertJobRecord => new InsertJobRecord($offload->connection, $offload->config, $this->table, $record));
    }

    /** Puts a job's record back on the queue, delayed, with the attempts it has made. */
    public function release(?string $queue, DatabaseJobRecord $job, DateInterval|DateTimeInterface|int $delay): int|string
    {
        return $this->pushToDatabase($queue, $job->payload, $delay, (int) $job->attempts);
    }

    /**
     * Inserts the job and answers with its id.
     */
    protected function pushToDatabase(?string $queue, string $payload, DateInterval|DateTimeInterface|int $delay = 0, int $attempts = 0): int|string
    {
        return $this->database->table($this->table)->insertGetId($this->buildDatabaseRecord(
            $this->getQueue($queue), $payload, $this->availableAt($delay), $attempts,
        ));
    }

    /**
     * @return array{queue: string, attempts: int, reserved_at: null, available_at: int, created_at: int, payload: string}
     */
    protected function buildDatabaseRecord(string $queue, string $payload, int $availableAt, int $attempts = 0): array
    {
        return [
            'queue' => $queue,
            'attempts' => $attempts,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => $this->currentTime(),
            'payload' => $payload,
        ];
    }

    public function pop(?string $queue = null): ?Job
    {
        $queue = $this->getQueue($queue);

        return $this->database->transaction(function () use ($queue): ?DatabaseJob {
            $job = $this->getNextAvailableJob($queue);

            return is_null($job) ? null : $this->marshalJob($queue, $job);
        });
    }

    protected function getNextAvailableJob(string $queue): ?DatabaseJobRecord
    {
        $job = $this->database->table($this->table)
            ->lock($this->getLockForPopping())
            ->where('queue', $queue)
            ->where(function ($query) {
                $this->isAvailable($query);
                $this->isReservedButExpired($query);
            })
            ->orderBy('id', 'asc')
            ->first();

        return $job ? new DatabaseJobRecord((object) $job) : null;
    }

    /**
     * SKIP LOCKED where the server supports it, so concurrent workers pass over each other's
     * rows; SQL Server's read-past hint; otherwise a plain lock for update.
     */
    protected function getLockForPopping(): string|bool
    {
        $engine = $this->database->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $version = (string) ($this->database->getConfig('version') ?? $this->database->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION));

        if (Str::contains($version, 'MariaDB')) {
            $engine = 'mariadb';
            $version = Str::before(Str::after($version, '5.5.5-'), '-');
        } elseif (Str::contains($version, ['vitess', 'PlanetScale'])) {
            $engine = 'vitess';
            $version = Str::before($version, '-');
        }

        if (($engine === 'mysql' && version_compare($version, '8.0.1', '>=')) ||
            ($engine === 'mariadb' && version_compare($version, '10.6.0', '>=')) ||
            ($engine === 'pgsql' && version_compare($version, '9.5', '>=')) ||
            ($engine === 'vitess' && version_compare($version, '19.0', '>='))) {
            return 'FOR UPDATE SKIP LOCKED';
        }

        if ($engine === 'sqlsrv') {
            return 'with(rowlock,updlock,readpast)';
        }

        return true;
    }

    protected function isAvailable($query): void
    {
        $query->where(fn ($query) => $query->whereNull('reserved_at')->where('available_at', '<=', $this->currentTime()));
    }

    protected function isReservedButExpired($query): void
    {
        $expiration = $this->currentTime() - $this->retryAfter;

        $query->orWhere(fn ($query) => $query->where('reserved_at', '<=', $expiration));
    }

    protected function marshalJob(string $queue, DatabaseJobRecord $job): DatabaseJob
    {
        return new DatabaseJob($this->container, $this, $this->markJobAsReserved($job), $this->connectionName, $queue);
    }

    protected function markJobAsReserved(DatabaseJobRecord $job): DatabaseJobRecord
    {
        $this->database->table($this->table)->where('id', $job->id)->update([
            'reserved_at' => $job->touch(),
            'attempts' => $job->increment(),
        ]);

        return $job;
    }

    public function deleteReserved(?string $queue, int|string $id): void
    {
        $this->database->transaction(function () use ($id) {
            if ($this->database->table($this->table)->lockForUpdate()->find($id)) {
                $this->database->table($this->table)->where('id', $id)->delete();
            }
        });
    }

    public function deleteAndRelease(?string $queue, DatabaseJob $job, DateInterval|DateTimeInterface|int $delay): void
    {
        $this->database->transaction(function () use ($queue, $job, $delay) {
            if ($this->database->table($this->table)->lockForUpdate()->find($job->getJobId())) {
                $this->database->table($this->table)->where('id', $job->getJobId())->delete();
            }

            $this->release($queue, $job->getJobRecord(), $delay);
        });
    }

    public function clear($queue): int
    {
        return $this->database->table($this->table)->where('queue', $this->getQueue($queue))->delete();
    }

    public function getQueue(?string $queue): string
    {
        return $queue ?: $this->default;
    }

    public function getDatabase(): Connection
    {
        return $this->database;
    }

    public function getTable(): string
    {
        return $this->table;
    }
}
