<?php

namespace Tests\Log\Fixtures;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

class LoggerSpy implements LoggerInterface
{
    use LoggerTrait;

    public array $logs = [];

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
