<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherGetDelay implements ShouldQueue
{
    public $delay = 10;

    public function handle()
    {
        //
    }

    public function withDelay()
    {
        return 20;
    }
}
