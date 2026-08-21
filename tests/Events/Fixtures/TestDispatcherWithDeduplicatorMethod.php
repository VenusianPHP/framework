<?php

namespace Tests\Events\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

class TestDispatcherWithDeduplicatorMethod implements ShouldQueue
{
    public function handle()
    {
        //
    }

    public function deduplicationId($payload, $queue)
    {
        return 'deduplication-id-method';
    }

    public function deduplicator($event)
    {
        return fn ($payload, $queue) => 'deduplicator-method';
    }
}
