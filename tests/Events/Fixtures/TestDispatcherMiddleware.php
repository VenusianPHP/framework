<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherMiddleware implements ShouldQueue
{
    public function middleware($a, $b)
    {
        return [new TestMiddleware($a, $b)];
    }

    public function handle($a, $b)
    {
        //
    }
}
