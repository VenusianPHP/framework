<?php

namespace Voyager\Contracts\IOPools;

interface Waiter
{

    /** The backend takes it directly, or the Waiter can relay it. */
    public function supports(WakeReason $kind): bool;

    /**
     * @param int|null $deadline absolute hrtime(true) of the soonest deadline, or null
     * @return array<string, list<Wake>>
     */
    public function wait(?int $deadline = null): array;
}