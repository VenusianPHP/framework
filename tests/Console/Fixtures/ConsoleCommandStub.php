<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class ConsoleCommandStub extends Command
{
    protected ?string $signature = 'foo:bar';

    protected string $description = 'This is a description about the command';

    protected $foo;

    public function __construct(FooClassStub $foo)
    {
        parent::__construct();

        $this->foo = $foo;
    }
}
