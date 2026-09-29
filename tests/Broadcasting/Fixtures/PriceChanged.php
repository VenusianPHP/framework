<?php

namespace Venusian\Tests\Broadcasting\Fixtures;

use Voyager\Broadcasting\Channel;
use Voyager\Contracts\Broadcasting\ShouldBroadcastNow;

/** Broadcast right away, with a payload of its own. */
class PriceChanged implements ShouldBroadcastNow
{
    public function __construct(public string $sku, public int $cents) {}

    public function broadcastOn(): Channel
    {
        return new Channel('prices');
    }

    public function broadcastWith(): array
    {
        return ['sku' => $this->sku, 'price' => $this->cents / 100];
    }
}
