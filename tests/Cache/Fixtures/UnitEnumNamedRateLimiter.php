<?php

namespace Tests\Cache\Fixtures;

/** A plain (non-backed) enum used as a rate limiter name. */
enum UnitEnumNamedRateLimiter
{
    case THIRD_PARTY;
}
