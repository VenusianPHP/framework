<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherWithMessageGroupProperty implements ShouldQueue
{
    public $messageGroup = 'group-property';

    public function handle()
    {
        //
    }
}
