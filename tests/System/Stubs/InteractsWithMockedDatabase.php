<?php

namespace Tests\System\Stubs;

use Voyager\System\Testing\Concerns\InteractsWithDatabase;

/**
 * InteractsWithDatabase with its connection resolution replaced by whatever the
 * test case has put on its `connection` property.
 *
 * Upstream overrides `getConnection()` on the TestCase itself; a Pest file has
 * no class to override on, so the override lives in a trait composed over the
 * real one — a trait's own method wins over the trait it uses.
 */
trait InteractsWithMockedDatabase
{
    use InteractsWithDatabase;

    protected function getConnection($connection = null, $table = null)
    {
        return $this->connection;
    }
}
