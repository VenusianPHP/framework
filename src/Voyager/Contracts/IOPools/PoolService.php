<?php

namespace Voyager\Contracts\IOPools;

use Voyager\NutsAndBolts\Collection;

interface PoolService extends PoolPump, PoolOperator
{
    public function http(): ?HttpResourceDriver;
    public function async(): ?AsyncResourceDriver;
}