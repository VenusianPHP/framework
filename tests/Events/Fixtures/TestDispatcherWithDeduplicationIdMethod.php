<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherWithDeduplicationIdMethod implements ShouldQueue
{
    public function handle()
    {
        //
    }

    public function deduplicationId($payload, $queue)
    {
        return 'deduplication-id-method';
    }
}
