<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherGetDelayDynamically implements ShouldQueue
{
    public $delay = 10;

    public function handle()
    {
        //
    }

    public function withDelay($event)
    {
        if ($event['useHighDelay']) {
            return 60;
        }

        return 20;
    }
}
