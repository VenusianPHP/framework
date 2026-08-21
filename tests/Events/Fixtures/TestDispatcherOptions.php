<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherOptions implements ShouldQueue
{
    public $maxExceptions = 1;

    public function retryUntil()
    {
        return now()->addHour(1);
    }

    public function tries()
    {
        return 5;
    }

    public function handle()
    {
        //
    }
}
