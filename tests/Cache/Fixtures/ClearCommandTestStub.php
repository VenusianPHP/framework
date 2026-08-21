<?php

namespace Tests\Cache\Fixtures;

use Voyager\Cache\Console\ClearCommand;

/** A ClearCommand that stubs out call() so it never shells out to another command. */
class ClearCommandTestStub extends ClearCommand
{
    public function call($command, array $arguments = []): int
    {
        return 0;
    }
}
