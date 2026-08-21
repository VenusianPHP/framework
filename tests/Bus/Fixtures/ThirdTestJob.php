<?php

namespace Tests\Bus\Fixtures;

use Voyager\Bus\Batchable;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\System\Bus\Dispatchable;
use Voyager\Bus\Queueable;

class ThirdTestJob implements ShouldQueue
{
    use Batchable, Dispatchable, Queueable;
}
