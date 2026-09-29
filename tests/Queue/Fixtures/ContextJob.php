<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Core\Queue\Queueable;
use Voyager\Log\Context\Repository;
use Voyager\Contracts\Queue\ShouldQueue;

/** Records the log context the worker handed it. */
class ContextJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $journal) {}

    public function handle(): mixed
    {
        return file_put_contents($this->journal, 'context: '.json_encode(app(Repository::class)->all())."\n", FILE_APPEND | LOCK_EX);
    }
}
