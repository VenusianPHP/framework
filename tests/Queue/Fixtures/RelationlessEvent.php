<?php

namespace Venusian\Tests\Queue\Fixtures;

use Voyager\Queue\SerializesModels;
use Voyager\Queue\Attributes\WithoutRelations;

/** Serializes its models without their relations, as a make:event class does. */
#[WithoutRelations]
class RelationlessEvent
{
    use SerializesModels;

    public function __construct(public string $line) {}
}
