<?php

namespace Tests\System\Stubs;

class SampleImplementation implements SampleInterface
{
    private $primitive;

    public function __construct($primitive)
    {
        $this->primitive = $primitive;
    }

    public function getPrimitive()
    {
        return $this->primitive;
    }
}
