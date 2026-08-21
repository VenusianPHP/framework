<?php

namespace Tests\Queue\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\System\Queue\Queueable;

class FakeSqsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        //
    }
}
