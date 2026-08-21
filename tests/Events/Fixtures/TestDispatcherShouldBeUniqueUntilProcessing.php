<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Voyager\Queue\InteractsWithQueue;
use Mockery;
use Voyager\Contracts\Cache\Lock;

class TestDispatcherShouldBeUniqueUntilProcessing implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use InteractsWithQueue;

    public static $lockReleasedBeforeHandling = null;
    public static $cache = null;
    public static $expectedLockKey = '';

    public function handle()
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->andReturn(true);
        static::$cache->shouldReceive('lock')
            ->with(static::$expectedLockKey, 10)
            ->andReturn($lock);

        static::$lockReleasedBeforeHandling = static::$cache->lock(static::$expectedLockKey, 10)->get();
    }
}
