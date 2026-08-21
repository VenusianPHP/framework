<?php

namespace Tests\Validation\deferred\Fixtures;

use Voyager\Database\Instrument\Model;

class InstrumentModelStub extends Model
{
    protected $table = 'table';
    protected $primaryKey = 'id_column';
    protected $guarded = [];
}
