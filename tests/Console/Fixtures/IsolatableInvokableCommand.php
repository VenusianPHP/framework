<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;
use Voyager\Contracts\Console\Isolatable;

class IsolatableInvokableCommand extends Command implements Isolatable
{
    public $ran = 0;

    public function __invoke()
    {
        $this->ran++;
    }
}
