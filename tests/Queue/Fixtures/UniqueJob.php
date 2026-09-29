<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Contracts\Queue\ShouldBeUnique;

/** Only one copy on the queue at a time, per line. */
class UniqueJob extends RecordJob implements ShouldBeUnique
{
    public function uniqueId(): string
    {
        return $this->line;
    }
}
