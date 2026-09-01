<?php

namespace Voyager\Contracts\IOPools;

use Voyager\IOPools\HttpResult;

/**
 * The transport behind HttpPool. multi-curl is the shipped truth for
 * I/O-bound work; the seam exists so other transports (an fd/serial pool's
 * sibling, an ext-parallel compute driver) can join without the pool
 * changing shape. Neither method may block.
 */
interface HttpDriver
{
    /**
     * Start one call.
     * @param array<string, string> $headers
     */
    public function dispatch(string $name, string $method, string $url, array $headers, ?string $body): void;

    /**
     * Advance in-flight work and hand back whatever finished.
     * @return list<HttpResult>
     */
    public function harvest(): array;

    /**
     * Bytes moved so far for every call still in flight. Transports that
     * cannot know answer an empty array.
     * @return array<string, array{now: int, total: int}>
     */
    public function progress(): array;
}
