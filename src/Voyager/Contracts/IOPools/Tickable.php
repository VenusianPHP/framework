<?php

namespace Voyager\Contracts\IOPools;

interface Tickable
{
    public function tick(): void;
}