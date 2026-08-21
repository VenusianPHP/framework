<?php

namespace Tests\Database\Fixtures\Auth;

use Voyager\Database\Instrument\Model;

/**
 * Stands in for Laravel's System\Auth\User in the blueprint tests.
 *
 * Auth is out of scope for this port, and these tests only need a model with
 * the default incrementing "id" key so `foreignIdFor()` yields "user_id".
 */
class User extends Model
{
    protected $table = 'users';
}
