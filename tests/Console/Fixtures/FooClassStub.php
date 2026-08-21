<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Scheduling\Schedule;

class FooClassStub
{
    protected $schedule;

    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;
    }
}
