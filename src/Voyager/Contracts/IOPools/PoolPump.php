<?php

namespace Voyager\Contracts\IOPools;

interface PoolPump
{
    public function push(QueuedIO $event): void;
}