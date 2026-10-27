<?php

namespace Voyager\Queue\Attributes;

use Attribute;
use UnitEnum;

use function Voyager\NutsAndBolts\enum_value;

#[Attribute(Attribute::TARGET_CLASS)]
class Connection
{
    /**
     * Create a new attribute instance.
     *
     * @param  UnitEnum|string  $connection
     */
    public function __construct(public UnitEnum|string $connection)
    {
        $this->connection = enum_value($connection);
    }
}
