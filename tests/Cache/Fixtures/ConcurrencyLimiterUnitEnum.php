<?php

namespace Tests\Cache\Fixtures;

/** A plain (non-backed) enum used as a funnel key. */
enum ConcurrencyLimiterUnitEnum
{
    case TestFunnel;
}
