<?php

namespace Voyager\Contracts\IOPools;

use Voyager\IOPools\Waiter\Wakes\WakingState;

interface WaiterBackendDriver
{
    public function supports(WakeReason $kind): bool;

    public function add(string $owner, Wake $wake): void;

    public function remove(string $owner, Wake $wake): void;

    /**
     * @return array<string, list<Wake>> owner => the wakes of theirs that fired
     */
    public function wait(?int $timeout_ns): array;
}