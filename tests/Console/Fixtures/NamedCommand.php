<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class NamedCommand extends Command
{
    protected ?string $name = 'command-name';
}
