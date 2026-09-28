<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Voyager\Contracts\Signals\Signal;

final class PingSignal implements Signal
{
    public function __construct(public readonly string $from) {}
}
