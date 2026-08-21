<?php

namespace Tests\Log\Fixtures;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use Monolog\Processor\UidProcessor;

class CustomChannelFactory
{
    public function __invoke()
    {
        return new Monolog(
            'uuid',
            [new StreamHandler(storage_path('logs/custom.log'))],
            [new UidProcessor]
        );
    }
}
