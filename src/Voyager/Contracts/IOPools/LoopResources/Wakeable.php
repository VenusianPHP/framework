<?php

namespace Voyager\Contracts\IOPools\LoopResources;

use Voyager\Contracts\IOPools\LoopResource;
use Voyager\Contracts\IOPools\Wake;

interface Wakeable extends LoopResource
{
    /** @return list<Wake> re-read every turn, so sockets can come and go */
    public function wakes(): array;

    /** @param list<Wake> $fired the subset of wakes() that fired this turn */
    public function woke(array $fired): void;
}