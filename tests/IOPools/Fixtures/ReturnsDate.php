<?php

namespace Venusian\Tests\IOPools\Fixtures;

use DateTimeImmutable;
use Voyager\Contracts\IOPools\ShouldPool;

/** An internal class in the payload: fine to serialize, fatal to copy across a thread. */
class ReturnsDate implements ShouldPool
{
    public function __construct(public readonly DateTimeImmutable $at) {}

    public function handle(): mixed
    {
        return $this->at->modify('+1 year');
    }
}
