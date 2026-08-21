<?php

namespace Tests\Bus\Fixtures;

enum QueueEnum: string
{
    case HIGH = 'high';
    case DEFAULT = 'default';
}
