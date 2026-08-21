<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherGetConnection implements ShouldQueue
{
    public $connection = 'my_connection';

    public function handle()
    {
        //
    }

    public function viaConnection()
    {
        return 'some_other_connection';
    }
}
