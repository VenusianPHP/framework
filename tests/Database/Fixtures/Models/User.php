<?php

namespace Tests\Database\Fixtures\Models;

use Voyager\Database\Instrument\Model;

/**
 * A user model with a non-standard primary key, so `foreignIdFor()` yields
 * "user_internal_id". Laravel extends its System\Auth\User here; Auth is out
 * of scope for this port and contributes nothing to the assertion.
 */
class User extends Model
{
    protected $table = 'users';

    protected $primaryKey = 'internal_id';
}
