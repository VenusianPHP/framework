<?php

namespace Voyager\Core\Queue;

use Voyager\Bus\Queueable as QueueableByBus;
use Voyager\Core\Bus\Dispatchable;
use Voyager\Queue\InteractsWithQueue;
use Voyager\Queue\Concerns\SerializesModels;

trait Queueable
{
    use Dispatchable, InteractsWithQueue, QueueableByBus, SerializesModels;
}
