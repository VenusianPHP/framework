<?php

namespace Tests\Bus\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class BusDispatcherTestSpecificQueueAndDelayCommand implements ShouldQueue
{
    public $queue = 'foo';
    public $delay = 10;
}
