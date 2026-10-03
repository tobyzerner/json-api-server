<?php

namespace Tobyz\Tests\JsonApiServer\laravel\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class Model extends EloquentModel
{
    public $timestamps = false;
    protected $guarded = [];
}
