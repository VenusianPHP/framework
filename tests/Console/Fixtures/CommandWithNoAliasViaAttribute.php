<?php

namespace Tests\Console\Fixtures;

use Symfony\Component\Console\Attribute\AsCommand;
use Voyager\Console\Command;

#[AsCommand('command-name')]
class CommandWithNoAliasViaAttribute extends Command
{
    //
}
