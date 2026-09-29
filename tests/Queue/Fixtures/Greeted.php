<?php

namespace Venusian\Tests\Queue\Fixtures;

/** A plain signal a queued listener hears. */
class Greeted
{
    public function __construct(public string $journal, public string $name) {}
}
