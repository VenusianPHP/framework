<?php

namespace Tests\Events\Fixtures;

class TestListener
{
    public static $counter = 0;

    public function handle()
    {
        self::$counter++;
    }
}
