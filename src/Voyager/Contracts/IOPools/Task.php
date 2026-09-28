<?php

namespace Voyager\Contracts\IOPools;

use Throwable;

interface Task extends Promise
{
    /** CancelledException lands at the fiber's suspend point. No-op once finished. */
    public function cancel(): void;
}