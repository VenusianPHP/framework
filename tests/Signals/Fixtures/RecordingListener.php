<?php

namespace Venusian\Tests\Signals\Fixtures;

class RecordingListener
{
    /** @var list<string> */
    public static array $heard = [];

    public function handle(PinChanged $signal): void
    {
        static::$heard[] = 'class-listener:'.$signal->name();
    }
}
