<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Contracts\Queue\ShouldBeUnique;

class TestDispatcherUniqueIdFromMethod implements ShouldQueue, ShouldBeUnique
{
    public function handle()
    {
        //
    }

    public function uniqueId($event)
    {
        return 'unique-id-'.$event['id'];
    }
}
