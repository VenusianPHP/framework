<?php

namespace Voyager\System\Queue;

use Voyager\Bus\Queueable as QueueableByBus;
use Voyager\System\Bus\Dispatchable;
use Voyager\Queue\InteractsWithQueue;
use Voyager\Queue\Concerns\SerializesModels;

trait Queueable
{
    use Dispatchable, InteractsWithQueue, QueueableByBus, SerializesModels;
}
