<?php

namespace Tests\Bus\Fixtures;

enum ConnectionEnum: string
{
    case SQS = 'sqs';
    case REDIS = 'redis';
}
