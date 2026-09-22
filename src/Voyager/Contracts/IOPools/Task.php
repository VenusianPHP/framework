<?php

namespace Voyager\Contracts\IOPools;

/** A promise for what an async() body returns, plus the handle to stop waiting for it. */
interface Task extends Promise
{
    /** CancelledException lands at the fiber's suspend point. No-op once finished. */
    public function cancel(): void;
}
