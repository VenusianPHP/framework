<?php

namespace Venusian\Tests\Http\Fixtures;

use Voyager\IOPools\Resources\WakeSource;
use Voyager\IOPools\Waiter\Wakes\Readable;

/** Another wake source on the loop: notes when its socket turned readable. */
final class SocketPairWatch extends WakeSource
{
    public ?int $woke_at = null;

    /** @param resource $stream */
    public function __construct(private readonly mixed $stream) {}

    public function wakes(): array
    {
        return is_null($this->woke_at) ? [new Readable($this->stream)] : [];
    }

    public function woke(array $fired): void
    {
        $this->woke_at = hrtime(true);
        fread($this->stream, 1024);
    }
}
