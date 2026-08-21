<?php

namespace Tests\Log\Fixtures;

use Monolog\LogRecord;
use Voyager\Contracts\Log\ContextLogProcessor;

class MyAddContextProcessor implements ContextLogProcessor
{
    public static bool $wasConstructed = false;

    public function __construct()
    {
        self::$wasConstructed = true;
    }

    #[\Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: array_merge($record->context, ['inside of MyAddContextProcessor' => true]));
    }
}
