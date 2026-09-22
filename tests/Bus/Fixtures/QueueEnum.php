<?php

namespace Venusian\Tests\Bus\Fixtures;

enum QueueEnum: string
{
    case HIGH = 'high';
    case DEFAULT = 'default';
}
