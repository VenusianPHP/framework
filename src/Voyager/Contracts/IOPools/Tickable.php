<?php

namespace Voyager\Contracts\IOPools;

interface Tickable extends Sourceable
{
    public function tick(): void;
}