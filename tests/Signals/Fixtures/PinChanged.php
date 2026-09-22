<?php

namespace Venusian\Tests\Signals\Fixtures;

use Voyager\Contracts\IOPools\Event;

/** A named signal: one class, many names. */
final class PinChanged extends Event implements HardwareSignal
{
    public function __construct(
        public readonly int $pin,
        public readonly string $edge,
    ) {}

    private ?string $uuid = null;

    public function uuid(): string
    {
        return $this->uuid ??= \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    public function name(): string
    {
        return "gpio.pin{$this->pin}.{$this->edge}";
    }
}
