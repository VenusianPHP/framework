<?php

namespace Tests\Events\Fixtures;

class TestEventListener
{
    public function handle($foo, $bar)
    {
        return 'baz';
    }

    public function onFooEvent($foo, $bar)
    {
        return 'baz';
    }
}
