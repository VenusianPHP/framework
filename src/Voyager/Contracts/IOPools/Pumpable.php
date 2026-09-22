<?php

namespace Voyager\Contracts\IOPools;

interface Pumpable
{
    /**
     * Hand over the mail queued since the last pump, oldest first.
     * @return array
     */
    public function pump(): array;
}
