<?php

namespace Voyager\Contracts\IOPools\LoopResources;

use Voyager\Contracts\IOPools\LoopResource;

interface Deadlined extends LoopResource
{
    public function fire(): void;
    public function dueAt(): ?int;
}