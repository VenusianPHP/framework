<?php

namespace Tests\Events\Fixtures;

class TestListenerInvokeyHandler
{
    public function __construct()
    {
        $_SERVER['__event.test'][] = '__construct';
    }

    public function __invoke()
    {
        $_SERVER['__event.test'][] = '__invoke';
    }

    public function handle()
    {
        $_SERVER['__event.test'][] = 'handle';
    }
}
