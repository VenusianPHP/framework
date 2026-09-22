<?php

namespace Venusian\Tests\Signals\Fixtures;

use Voyager\Contracts\IOPools\Event;

/** A named signal whose name IS its class: the double-fire trap. */
final class SelfNamed extends Event
{
    private ?string $uuid = null;

    public function uuid(): string
    {
        return $this->uuid ??= \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    public function name(): string
    {
        return self::class;
    }
}
