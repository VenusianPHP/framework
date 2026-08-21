<?php

declare(strict_types=1);

namespace Tests\Console\Fixtures;

use Voyager\Contracts\Queue\ShouldQueue;

final class JobToTestWithSchedule implements ShouldQueue
{
}
