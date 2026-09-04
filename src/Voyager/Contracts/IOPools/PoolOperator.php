<?php

namespace Voyager\Contracts\IOPools;

use Voyager\NutsAndBolts\Collection;

interface PoolOperator
{
    public function pump(): void;
    public function drain(): Collection;

}