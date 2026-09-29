<?php

namespace Voyager\Hashing;

use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/** Checks a value against a hash in a worker: as much CPU as making the hash did. */
final class CheckHashGig implements ShouldPool
{
    use HashesInWorkers;

    /**
     * @param string|null $driver null: the worker's configured driver
     * @param array<string, mixed>|null $config the caller's hashing config; null: the worker's own
     */
    public function __construct(
        #[\SensitiveParameter] public readonly string $value,
        public readonly ?string $hashed_value,
        public readonly ?string $driver = null,
        public readonly array $options = [],
        public readonly ?array $config = null,
    ) {}

    public function handle(): bool
    {
        return $this->hasher()->check($this->value, $this->hashed_value, $this->options);
    }
}
