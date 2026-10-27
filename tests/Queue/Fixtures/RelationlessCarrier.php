<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Core\Queue\Queueable;
use Voyager\Database\Instrument\Model;
use Voyager\Queue\Attributes\WithoutRelations;

/** A Queueable job that serializes its models without their relations. */
#[WithoutRelations]
class RelationlessCarrier
{
    use Queueable;

    public function __construct(public Model $model) {}
}
