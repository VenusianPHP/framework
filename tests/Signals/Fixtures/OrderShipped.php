<?php

namespace Venusian\Tests\Signals\Fixtures;

use Voyager\Contracts\Signals\Signal;

/** A plain signal, named after itself, the way Laravel does it. */
final class OrderShipped implements Signal
{
    public function __construct(public readonly string $order = 'A-1') {}
}
