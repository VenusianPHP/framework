<?php

namespace Tests\Events\Fixtures;

class TestListener2Falser
{
    public function __construct()
    {
        $_SERVER['__event.test'][] = 'cons-2-falser';
    }

    public function handle()
    {
        $_SERVER['__event.test'][] = 'handle-2-falser';

        return false;
    }
}
