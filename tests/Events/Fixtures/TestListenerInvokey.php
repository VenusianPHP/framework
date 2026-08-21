<?php

namespace Tests\Events\Fixtures;

class TestListenerInvokey
{
    public function __construct()
    {
        $_SERVER['__event.test'][] = '__construct';
    }

    public function __invoke($payload)
    {
        $_SERVER['__event.test'][] = '__invoke_'.$payload;

        return false;
    }
}
