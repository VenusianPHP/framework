<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class CommandWithAliasViaProperty extends Command
{
    public $name = 'command-name';

    public $aliases = ['command-alias'];
}
