<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class AliasedCommand extends Command
{
    protected ?string $name = 'foo:bar';

    protected ?array $aliases = ['bar:baz', 'baz:qux'];
}
