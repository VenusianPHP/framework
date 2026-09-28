<?php

namespace Voyager\Contracts\IOPools\LoopResources;

use Voyager\Contracts\IOPools\LoopResource;

interface Resumable extends LoopResource
{
    public function resume(): bool;
    public function pending(): bool;
}