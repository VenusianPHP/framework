<?php

namespace Tests\Events\Fixtures;

class TestListener1
{
    public function __construct()
    {
        $_SERVER['__event.test'][] = 'cons-1';
    }

    public function handle()
    {
        $_SERVER['__event.test'][] = 'handle-1';

        return 'resp-1';
    }
}
