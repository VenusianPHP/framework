<?php

namespace Voyager\Contracts\IOPools\LoopResources;

use Voyager\Contracts\IOPools\LoopResource;

interface Tickable extends LoopResource
{
    public function tick(): void;
}