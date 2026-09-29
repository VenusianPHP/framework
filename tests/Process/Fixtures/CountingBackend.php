<?php

namespace Venusian\Tests\Process\Fixtures;

use Voyager\Contracts\IOPools\Wake;
use Voyager\Contracts\IOPools\WakeReason;
use Voyager\Contracts\IOPools\WaiterBackendDriver;

/** A waiter backend that counts the waits it is asked for, around a real one. */
final class CountingBackend implements WaiterBackendDriver
{
    public int $waits = 0;

    public function __construct(private readonly WaiterBackendDriver $inner) {}

    public function supports(WakeReason $kind): bool
    {
        return $this->inner->supports($kind);
    }

    public function add(string $owner, Wake $wake): void
    {
        $this->inner->add($owner, $wake);
    }

    public function remove(string $owner, Wake $wake): void
    {
        $this->inner->remove($owner, $wake);
    }

    public function wait(?int $timeout_ns): array
    {
        $this->waits++;

        return $this->inner->wait($timeout_ns);
    }
}
