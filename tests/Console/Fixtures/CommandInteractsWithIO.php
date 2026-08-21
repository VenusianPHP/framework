<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;
use Voyager\Console\Concerns\InteractsWithIO;

class CommandInteractsWithIO extends Command
{
    use InteractsWithIO;
}
