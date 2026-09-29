<?php

namespace Voyager\Queue\Jobs;

use Voyager\NutsAndBolts\Concerns\InteractsWithTime;

/**
 * A job's row. Reserving it bumps its attempts and stamps reserved_at here, so the job reads
 * the values its update wrote.
 *
 * @property-read int|string $id
 * @property-read string $queue
 * @property-read string $payload
 * @property-read int $attempts
 * @property-read int|null $reserved_at
 * @property-read int $available_at
 * @property-read int $created_at
 */
class DatabaseJobRecord
{
    use InteractsWithTime;

    public function __construct(protected object $record) {}

    public function increment(): int
    {
        $this->record->attempts = (int) $this->record->attempts + 1;

        return $this->record->attempts;
    }

    public function touch(): int
    {
        $this->record->reserved_at = $this->currentTime();

        return $this->record->reserved_at;
    }

    public function __get(string $key): mixed
    {
        return $this->record->{$key};
    }
}
