<?php

namespace Tests\Filesystem\Fixtures;

class StringableObjecty
{
    public function __toString()
    {
        return 'objecty';
    }
}
