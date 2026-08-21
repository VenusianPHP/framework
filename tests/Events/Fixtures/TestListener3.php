<?php

namespace Tests\Events\Fixtures;

class TestListener3
{
    public function __construct()
    {
        $_SERVER['__event.test'][] = 'cons-3';
    }

    public function handle()
    {
        $_SERVER['__event.test'][] = 'handle-3';
    }
}
