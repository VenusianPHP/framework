<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class IsolatableNamedCommand extends Command
{
    protected ?string $name = 'command-name';

    public function isolatableId()
    {
        return 'isolated';
    }
}
