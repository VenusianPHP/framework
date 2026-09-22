<?php

namespace Voyager\Contracts\IOPools;

interface StreamWatchable extends Tickable
{
    public function streams(): array;
    
}