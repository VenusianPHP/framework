<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Core\Queue\Queueable;
use Voyager\Queue\SerializesModels;
use Voyager\Database\Instrument\Model;

/** A job in Laravel's shape, Queueable and SerializesModels both, carrying a model. */
class ModelCarrier
{
    use Queueable, SerializesModels;

    public function __construct(public Model $model) {}
}
