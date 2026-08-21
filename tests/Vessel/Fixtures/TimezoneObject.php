<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Vessel\Attributes\Config;

final class TimezoneObject
{
    public function __construct(
        #[Config('app.timezone')] public readonly ?string $timezone
    ) {
        //
    }
}
