<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherWithMessageGroupMethod implements ShouldQueue
{
    public $messageGroup = 'group-property';

    public function handle()
    {
        //
    }

    public function messageGroup($event)
    {
        return 'group-method';
    }
}
