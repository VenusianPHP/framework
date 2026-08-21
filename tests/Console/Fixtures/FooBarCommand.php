<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class FooBarCommand extends Command
{
    protected ?string $signature = 'foo:bar';

    public function handle()
    {
    }
}
