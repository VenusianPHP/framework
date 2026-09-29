<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Bus\Batchable;
use Voyager\Core\Queue\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;

/** A job in a batch: records its line with the batch it belongs to. */
class BatchedJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public string $journal, public string $line) {}

    public function handle(): mixed
    {
        return file_put_contents($this->journal, "{$this->line} in {$this->batch()?->name}\n", FILE_APPEND | LOCK_EX);
    }
}
