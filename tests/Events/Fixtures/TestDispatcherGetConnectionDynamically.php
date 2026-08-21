<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherGetConnectionDynamically implements ShouldQueue
{
    public function handle()
    {
        //
    }

    public function viaConnection($event)
    {
        if ($event['shouldUseRedisConnection']) {
            return 'redis';
        }

        return 'sqs';
    }
}
