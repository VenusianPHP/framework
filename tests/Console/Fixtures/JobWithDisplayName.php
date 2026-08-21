<?php

declare(strict_types=1);

namespace Tests\Console\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

final class JobWithDisplayName implements ShouldQueue
{
    public function displayName(): string
    {
        return 'testJob-123';
    }
}
