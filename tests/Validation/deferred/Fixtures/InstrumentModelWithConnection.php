<?php

namespace Tests\Validation\deferred\Fixtures;

use Tests\Validation\deferred\Fixtures\InstrumentModelStub;

class InstrumentModelWithConnection extends InstrumentModelStub
{
    protected $connection = 'mysql';
}
