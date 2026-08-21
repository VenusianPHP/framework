<?php

namespace Tests\Events\Fixtures;

class TestEvent
{
    public function __construct()
    {
        $_SERVER['__event.test'][] = 'cons-event-1';
    }
}
