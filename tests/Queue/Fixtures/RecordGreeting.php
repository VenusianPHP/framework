<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Queue\InteractsWithQueue;
use Voyager\Contracts\Queue\ShouldQueue;

/** A queued listener: it runs as a job and records what it heard. */
class RecordGreeting implements ShouldQueue
{
    use InteractsWithQueue;

    public ?string $connection = 'sync';

    public function handle(?Greeted $signal = null): mixed
    {
        return file_put_contents($signal->journal, "greeted {$signal->name} as a job: ".($this->job ? 'yes' : 'no')."\n", FILE_APPEND | LOCK_EX);
    }
}
