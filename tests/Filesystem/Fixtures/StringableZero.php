<?php

namespace Venusian\Tests\Filesystem\Fixtures;

class StringableZero
{
    public function __toString()
    {
        return '0';
    }
}
