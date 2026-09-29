<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Queue\InteractsWithQueue;
use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\Contracts\Queue\ShouldBeUnique;

/** A unique queued listener: one copy per greeted name waits on the database queue. */
class UniqueGreeting implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;

    public ?string $connection = 'database';

    public function uniqueId(Greeted $signal): string
    {
        return $signal->name;
    }

    public function handle(?Greeted $signal = null): mixed
    {
        return file_put_contents($signal->journal, "unique greeting for {$signal->name}\n", FILE_APPEND | LOCK_EX);
    }
}
