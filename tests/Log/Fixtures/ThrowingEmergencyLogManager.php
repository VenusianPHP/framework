<?php

namespace Venusian\Tests\Log\Fixtures;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Voyager\Log\LogManager;

class ThrowingEmergencyLogManager extends LogManager
{
    protected function createEmergencyLogger(): LoggerInterface
    {
        throw new RuntimeException('Emergency logger was created.');
    }
}
