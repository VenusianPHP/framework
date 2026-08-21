<?php

namespace Tests\Events\Fixtures;

class TestListener2
{
    public function __construct()
    {
        $_SERVER['__event.test'][] = 'cons-2';
    }

    public function handle()
    {
        $_SERVER['__event.test'][] = 'handle-2';

        return 'resp-2';
    }
}
