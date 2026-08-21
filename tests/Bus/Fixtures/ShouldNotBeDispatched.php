<?php

namespace Tests\Bus\Fixtures;

use Voyager\Bus\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Queue\InteractsWithQueue;
use RuntimeException;

class ShouldNotBeDispatched implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function handle()
    {
        throw new RuntimeException('This should not be run');
    }
}
