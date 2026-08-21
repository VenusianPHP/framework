<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Contracts\Queue\ShouldBeUnique;
use Voyager\Contracts\Cache\Repository as Cache;

class TestDispatcherShouldBeUniqueWithCustomCache implements ShouldQueue, ShouldBeUnique
{
    public static $cache = null;

    public function handle()
    {
        //
    }

    public function uniqueId()
    {
        return 'unique-listener-id';
    }

    public function uniqueFor()
    {
        return 60;
    }

    public function uniqueVia(): Cache
    {
        return static::$cache;
    }
}
