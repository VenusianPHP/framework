<?php

namespace Tests\Events\Fixtures;

class ExampleSubscriber
{
    public function subscribe($e)
    {
        // There would be no error if a non-array is returned.
        return '(O_o)';
    }
}
