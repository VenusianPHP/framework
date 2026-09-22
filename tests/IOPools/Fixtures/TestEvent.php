<?php

namespace Venusian\Tests\IOPools\Fixtures;

use Ramsey\Uuid\Uuid;
use Voyager\Contracts\IOPools\Event;

final class TestEvent extends Event
{
    private readonly string $uuid;

    public function __construct(
        private readonly string $name,
        public readonly mixed $payload = null,
    ) {
        $this->uuid = Uuid::uuid4()->toString();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function toData(): array
    {
        return ['name' => $this->name, 'payload' => $this->payload];
    }
}
