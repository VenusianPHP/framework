<?php

namespace Voyager\Contracts\IOPools;

interface Sendable
{
    /**
     * @return array<string, mixed>
     */
    public function toSendable(): array;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromSendable(array $data): static;
}