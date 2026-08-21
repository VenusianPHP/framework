<?php

namespace Tests\Cache\Fixtures;

/** A string-backed enum used as a funnel key. */
enum ConcurrencyLimiterBackedEnum: string
{
    case TestFunnel = 'test-funnel';
}
