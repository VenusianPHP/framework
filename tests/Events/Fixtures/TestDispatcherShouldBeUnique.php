<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Contracts\Queue\ShouldBeUnique;

class TestDispatcherShouldBeUnique implements ShouldQueue, ShouldBeUnique
{
    public $uniqueId = 'unique-listener-id';

    public $uniqueFor = 60;

    public function handle()
    {
        //
    }
}
