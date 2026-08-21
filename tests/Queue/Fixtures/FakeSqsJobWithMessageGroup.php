<?php

namespace Tests\Queue\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;
use Voyager\System\Queue\Queueable;

class FakeSqsJobWithMessageGroup implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        //
    }

    /**
     * Message group method called by SqsQueue.
     *
     * @return string
     */
    public function messageGroup(): string
    {
        return 'group-1';
    }
}
