<?php

namespace Venusian\Tests\Queue\Fixtures;

use RuntimeException;
use Voyager\Core\Queue\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;

/** Always throws; tries once. */
class FailingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $journal) {}

    public function handle(): mixed
    {
        throw new RuntimeException('this job always fails');
    }

    public function failed(\Throwable $e): void
    {
        file_put_contents($this->journal, "failed: {$e->getMessage()}\n", FILE_APPEND | LOCK_EX);
    }
}
