<?php

namespace Jimoh\ActivityFeed\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table    = 'test_users';
    protected $fillable = ['name', 'email'];
}
