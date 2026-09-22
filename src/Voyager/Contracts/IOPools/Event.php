<?php

namespace Voyager\Contracts\IOPools;

use Voyager\Contracts\Signals\NamedSignal;

abstract class Event implements NamedSignal
{
    abstract public function uuid(): string;

    /** What crosses a wire. Default: the public properties. Override for anything richer. */
    public function toData(): array
    {
        return get_object_vars($this);
    }

    /** Rebuild from what toData() sent. Default: constructor arguments by name. */
    public static function fromData(array $data): static
    {
        return new static(...$data);
    }
}