<?php

namespace Tests\Database\Fixtures\Models;

use Voyager\Database\Instrument\Concerns\HasUlids;
use Voyager\Database\Instrument\Model;

class InstrumentModelUsingUlid extends Model
{
    use HasUlids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'model';

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return 'model_using_ulid_id';
    }
}
