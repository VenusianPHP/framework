<?php

namespace Voyager\Hashing;

use Voyager\Contracts\IOPools\ShouldPool;

/**
 * A hash is a quarter second of CPU by design. Under a window that's a dropped frame,
 * so ship it to a worker: $pool->submit(new HashGig($value))->wait().
 */
final class HashGig implements ShouldPool
{
    public function __construct(
        #[\SensitiveParameter] public readonly string $value,
        public readonly ?string $driver = null,        // null = config('hashing.driver')
        public readonly array $options = [],
    ) {}

    public function handle(): string
    {
        return app('hash')->driver($this->driver)->make($this->value, $this->options);
    }
}
