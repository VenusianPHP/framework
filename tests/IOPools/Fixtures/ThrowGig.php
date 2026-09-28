<?php

namespace Venusian\Tests\IOPools\Fixtures;

use DomainException;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;

final class ThrowGig implements ShouldPool
{
    public function __construct(public readonly string $message) {}

    public function handle(): mixed
    {
        throw new DomainException($this->message);
    }
}
