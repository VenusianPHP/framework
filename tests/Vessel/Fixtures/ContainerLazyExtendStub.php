<?php

namespace Tests\Vessel\Fixtures;

class ContainerLazyExtendStub
{
    public static $initialized = false;

    public function init()
    {
        static::$initialized = true;
    }
}
