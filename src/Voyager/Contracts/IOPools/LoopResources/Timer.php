<?php

namespace Voyager\Contracts\IOPools\LoopResources;

interface Timer extends Deadlined
{
    public function interval(): ?int;

    public function cancel(): void;

    public function cancelled(): bool;
}