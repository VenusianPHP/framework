<?php

namespace Voyager\Hashing;

use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

/**
 * A hash is a quarter second of CPU by design. Under a window that's a dropped frame, so ship it
 * to a worker: HashManager::makeAsync(), or app('process-workers')->submit(new HashGig($value)).
 */
final class HashGig implements ShouldPool
{
    use HashesInWorkers;

    /**
     * @param string|null $driver null: the worker's configured driver
     * @param array<string, mixed>|null $config the caller's hashing config, so the worker hashes as the caller would; null: the worker's own
     */
    public function __construct(
        #[\SensitiveParameter] public readonly string $value,
        public readonly ?string $driver = null,
        public readonly array $options = [],
        public readonly ?array $config = null,
    ) {}

    public function handle(): string
    {
        return $this->hasher()->make($this->value, $this->options);
    }
}
