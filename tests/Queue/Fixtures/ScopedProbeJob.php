<?php

namespace Venusian\Tests\Queue\Fixtures;

use ArrayObject;
use Voyager\Core\Queue\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;

/**
 * Writes how many marks the scoped 'probe' bag already held, then adds its own: a worker that
 * resets the scope between jobs hands every job an empty bag.
 */
class ScopedProbeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $journal, public string $line) {}

    public function handle(): mixed
    {
        /** @var ArrayObject $probe */
        $probe = app('probe');
        $seen = count($probe);
        $probe[] = $this->line;

        return file_put_contents($this->journal, "{$this->line} saw {$seen}\n", FILE_APPEND | LOCK_EX);
    }
}
