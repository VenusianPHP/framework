<?php

namespace Venusian\Tests\Database\IOPools;

use Voyager\Database\Instrument\Model;

class PoolUser extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];

    public function posts()
    {
        return $this->hasMany(PoolPost::class, 'user_id');
    }
}
