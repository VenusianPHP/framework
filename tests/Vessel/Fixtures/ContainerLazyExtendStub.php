<?php

namespace Venusian\Tests\Vessel\Fixtures;

class ContainerLazyExtendStub
{
    public static $initialized = false;

    public function init()
    {
        static::$initialized = true;
    }
}
