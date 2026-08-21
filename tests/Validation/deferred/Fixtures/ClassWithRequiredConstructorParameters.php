<?php

namespace Tests\Validation\deferred\Fixtures;

class ClassWithRequiredConstructorParameters
{
    private $bar;
    private $baz;

    public function __construct($bar, $baz)
    {
        $this->bar = $bar;
        $this->baz = $baz;
    }
}
