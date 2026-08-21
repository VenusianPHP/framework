<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class CommandWithNoAliasViaProperty extends Command
{
    public $name = 'command-name';
}
