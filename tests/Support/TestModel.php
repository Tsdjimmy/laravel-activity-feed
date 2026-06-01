<?php

namespace Jimoh\ActivityFeed\Tests\Support;

use Jimoh\ActivityFeed\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    use LogsActivity;

    protected $table      = 'test_models';
    protected $fillable   = ['name', 'status', 'secret'];
    public    $timestamps = true;
}
