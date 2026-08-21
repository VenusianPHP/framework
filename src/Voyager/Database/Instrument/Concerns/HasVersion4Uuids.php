<?php

namespace Voyager\Database\Instrument\Concerns;

use Voyager\NutsAndBolts\DataObjects\Str;

trait HasVersion4Uuids
{
    use HasUuids;

    /**
     * Generate a new UUID (version 4) for the model.
     *
     * @return string
     */
    public function newUniqueId()
    {
        return (string) Str::orderedUuid();
    }
}
