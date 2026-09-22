<?php

namespace Venusian\Tests\Bus\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class BusDispatcherTestSpecificQueueAndDelayCommand implements ShouldQueue
{
    public function handle(): mixed
    {
        return null;
    }

    public $queue = 'foo';
    public $delay = 10;
}
