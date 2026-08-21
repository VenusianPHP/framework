<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherGetQueue implements ShouldQueue
{
    public $queue = 'my_queue';

    public function handle()
    {
        //
    }

    public function viaQueue()
    {
        return 'some_other_queue';
    }
}
