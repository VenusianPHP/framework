<?php

namespace Tests\Validation\deferred\Fixtures;

use Voyager\Database\Instrument\Model;

class PrefixedTableInstrumentModelStub extends Model
{
    protected $table = 'public.table';
    protected $primaryKey = 'id_column';
    protected $guarded = [];
}
