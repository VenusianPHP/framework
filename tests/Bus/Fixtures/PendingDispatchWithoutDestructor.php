<?php

namespace Tests\Bus\Fixtures;

use Voyager\System\Bus\PendingDispatch;

class PendingDispatchWithoutDestructor extends PendingDispatch
{
    public function __destruct()
    {
        // Prevent the job from being dispatched
    }
}
