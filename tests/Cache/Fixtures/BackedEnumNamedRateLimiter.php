<?php

namespace Tests\Cache\Fixtures;

/** A string-backed enum used as a rate limiter name. */
enum BackedEnumNamedRateLimiter: string
{
    case API = 'api';
}
