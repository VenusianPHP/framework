<?php

namespace Venusian\Tests\Broadcasting\Fixtures;

use Voyager\Broadcasting\Channel;
use Voyager\Broadcasting\PrivateChannel;
use Voyager\Contracts\Broadcasting\ShouldBroadcast;

/** Broadcast through the queue, its public properties as the payload. */
class OrderShipped implements ShouldBroadcast
{
    public function __construct(public int $order, public string $carrier, public ?string $connection = null) {}

    public function broadcastOn(): array
    {
        return [new Channel('orders'), new PrivateChannel("customer.{$this->order}")];
    }

    public function broadcastAs(): string
    {
        return 'order.shipped';
    }

    public function broadcastWhen(): bool
    {
        return $this->carrier !== 'none';
    }
}
