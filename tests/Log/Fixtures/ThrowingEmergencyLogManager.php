<?php

namespace Tests\Log\Fixtures;

use RuntimeException;
use Voyager\Log\LogManager;

class ThrowingEmergencyLogManager extends LogManager
{
    protected function createEmergencyLogger()
    {
        throw new RuntimeException('Emergency logger was created.');
    }
}
