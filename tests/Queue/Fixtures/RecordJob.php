<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Core\Queue\Queueable;
use Voyager\Contracts\Queue\ShouldQueue;

/** Writes its line, and the process that ran it, to the journal. */
class RecordJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $journal, public string $line) {}

    public function handle(): mixed
    {
        return file_put_contents($this->journal, $this->line.' @'.getmypid()."\n", FILE_APPEND | LOCK_EX);
    }
}
