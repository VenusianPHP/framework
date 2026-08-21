<?php

namespace Tests\Bus\Fixtures;

class BusDispatcherBasicCommand
{
    public $name;

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function handle(BusInjectionStub $stub)
    {
        //
    }
}
