<?php

namespace Venusian\Tests\Bus\Fixtures;

use Voyager\Core\Bus\PendingDispatch;

class PendingDispatchWithoutDestructor extends PendingDispatch
{
    public function __destruct()
    {
        // Prevent the job from being dispatched
    }
}
